<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorOath\Service;

use OCA\TwoFactorOath\AppInfo\Application;
use OCA\TwoFactorOath\Constants;
use OCP\Authentication\TwoFactorAuth\IRegistry;
use OCP\BackgroundJob\IJobList;
use OCP\IAppConfig;
use OCP\IUser;
use OCP\Notification\IManager as INotificationManager;
use Override;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * twofactor_backupcodes ships with the server and can be neither disabled nor
 * restricted to groups, so every user can generate backup codes and with them
 * sign in without the token an administrator provisioned. While the rule is on,
 * a managed user's backup codes are deleted and their provider state is set to
 * disabled, which also drops them from the login challenge. The app's reminder
 * to generate codes is withdrawn as well, since it would ask for exactly that.
 * Generating new codes is refused by BackupCodesMiddleware, and the personal
 * settings hide the backup code section.
 */
final class BackupCodesGuard implements IBackupCodesGuard {
	/** twofactor_backupcodes provider id in the two-factor registry. */
	private const PROVIDER_ID = 'backup_codes';

	/**
	 * Internal Nextcloud manager that disables a provider for a user (the same path
	 * as `occ twofactorauth:disable`). Resolved lazily by name so the app does not
	 * carry a compile-time dependency on a non-OCP class.
	 */
	private const PROVIDER_MANAGER_CLASS = 'OC\\Authentication\\TwoFactorAuth\\ProviderManager';

	/**
	 * twofactor_backupcodes schedules this job per user whenever a second factor is
	 * registered; it sends the "generate backup codes" notification every 14 days
	 * until the user has codes. Named, not imported, for the same reason as above.
	 */
	private const REMINDER_JOB_CLASS = 'OCA\\TwoFactorBackupCodes\\BackgroundJob\\RememberBackupCodesJob';

	/** App and object the reminder notification is filed under. */
	private const REMINDER_APP_ID = 'twofactor_backupcodes';
	private const REMINDER_OBJECT_TYPE = 'create';
	private const REMINDER_OBJECT_ID = 'codes';

	public function __construct(
		private readonly IAppConfig $appConfig,
		private readonly IPolicyService $policyService,
		private readonly IRegistry $registry,
		private readonly IJobList $jobList,
		private readonly INotificationManager $notificationManager,
		private readonly ContainerInterface $container,
		private readonly LoggerInterface $logger,
	) {
	}

	#[Override]
	public function isEnabled(): bool {
		return $this->appConfig->getValueBool(Application::APP_ID, Constants::CONFIG_BLOCK_BACKUP_CODES);
	}

	#[Override]
	public function appliesTo(IUser $user): bool {
		return $this->isEnabled() && $this->policyService->isManaged($user);
	}

	#[Override]
	public function enforce(IUser $user): void {
		if (!$this->appliesTo($user)) {
			return;
		}

		$states = $this->registry->getProviderStates($user);
		if (($states[self::PROVIDER_ID] ?? false) === true) {
			$this->revokeCodes($user);
		}

		$this->withdrawReminder($user);
	}

	#[Override]
	public function enforceAll(): void {
		if (!$this->isEnabled()) {
			return;
		}

		foreach ($this->policyService->listManagedUsers() as $user) {
			$this->enforce($user);
		}
	}

	private function revokeCodes(IUser $user): void {
		$exception = null;
		try {
			$providerManager = $this->container->get(self::PROVIDER_MANAGER_CLASS);
			/** @psalm-suppress MixedMethodCall */
			$revoked = $providerManager->tryDisableProviderFor(self::PROVIDER_ID, $user);
		} catch (Throwable $e) {
			$revoked = false;
			$exception = $e;
		}

		if ($revoked !== true) {
			// The codes stay usable, which is what the rule forbids, so this is an error.
			$this->logger->error('Could not revoke the backup codes of a managed user', [
				'userId' => $user->getUID(),
				'exception' => $exception,
			]);
		}
	}

	private function withdrawReminder(IUser $user): void {
		$argument = ['uid' => $user->getUID()];
		if (!$this->jobList->has(self::REMINDER_JOB_CLASS, $argument)) {
			return;
		}

		$this->jobList->remove(self::REMINDER_JOB_CLASS, $argument);

		$notification = $this->notificationManager->createNotification();
		$notification->setApp(self::REMINDER_APP_ID)
			->setUser($user->getUID())
			->setObject(self::REMINDER_OBJECT_TYPE, self::REMINDER_OBJECT_ID);
		$this->notificationManager->markProcessed($notification);
	}
}
