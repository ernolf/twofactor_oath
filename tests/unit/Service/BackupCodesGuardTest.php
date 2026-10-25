<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorOath\Tests\Unit\Service;

use OCA\TwoFactorOath\Constants;
use OCA\TwoFactorOath\Service\BackupCodesGuard;
use OCA\TwoFactorOath\Service\IPolicyService;
use OCP\Authentication\TwoFactorAuth\IRegistry;
use OCP\BackgroundJob\IJobList;
use OCP\IAppConfig;
use OCP\IUser;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Unit tests for the backup-code rule. The server's ProviderManager is internal,
 * so a stand-in with the same method records what the guard asks it to disable.
 */
final class BackupCodesGuardTest extends TestCase {
	private const PROVIDER_MANAGER_CLASS = 'OC\\Authentication\\TwoFactorAuth\\ProviderManager';
	private const REMINDER_JOB_CLASS = 'OCA\\TwoFactorBackupCodes\\BackgroundJob\\RememberBackupCodesJob';

	private IAppConfig&MockObject $appConfig;
	private IPolicyService&MockObject $policyService;
	private IRegistry&MockObject $registry;
	private IJobList&MockObject $jobList;
	private INotificationManager&MockObject $notificationManager;
	private ContainerInterface&MockObject $container;
	private LoggerInterface&MockObject $logger;
	private BackupCodesGuard $guard;

	protected function setUp(): void {
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->policyService = $this->createMock(IPolicyService::class);
		$this->registry = $this->createMock(IRegistry::class);
		$this->jobList = $this->createMock(IJobList::class);
		$this->notificationManager = $this->createMock(INotificationManager::class);
		$this->container = $this->createMock(ContainerInterface::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->guard = new BackupCodesGuard(
			$this->appConfig,
			$this->policyService,
			$this->registry,
			$this->jobList,
			$this->notificationManager,
			$this->container,
			$this->logger,
		);
	}

	private function ruleIs(bool $enabled): void {
		$this->appConfig->method('getValueBool')
			->with('twofactor_oath', Constants::CONFIG_BLOCK_BACKUP_CODES)
			->willReturn($enabled);
	}

	private function managedUser(string $userId, bool $hasCodes): IUser&MockObject {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->policyService->method('isManaged')->willReturn(true);
		$this->registry->method('getProviderStates')->willReturn(['oath' => true, 'backup_codes' => $hasCodes]);

		return $user;
	}

	/** Stand-in for the server's ProviderManager that records each call. */
	private function providerManager(bool $result): object {
		$providerManager = new class($result) {
			/** @var list<array{string, IUser}> */
			public array $calls = [];

			public function __construct(
				private readonly bool $result,
			) {
			}

			public function tryDisableProviderFor(string $providerId, IUser $user): bool {
				$this->calls[] = [$providerId, $user];

				return $this->result;
			}
		};
		$this->container->method('get')->with(self::PROVIDER_MANAGER_CLASS)->willReturn($providerManager);

		return $providerManager;
	}

	public function testRuleIsOffByDefault(): void {
		$this->appConfig->method('getValueBool')->willReturn(false);

		$this->assertFalse($this->guard->isEnabled());
	}

	public function testRuleAppliesToAManagedUser(): void {
		$this->ruleIs(true);
		$this->policyService->method('isManaged')->willReturn(true);

		$this->assertTrue($this->guard->appliesTo($this->createMock(IUser::class)));
	}

	public function testRuleDoesNotApplyToAnUnmanagedUser(): void {
		$this->ruleIs(true);
		$this->policyService->method('isManaged')->willReturn(false);

		$this->assertFalse($this->guard->appliesTo($this->createMock(IUser::class)));
	}

	public function testRuleDoesNotApplyWhileItIsOff(): void {
		$this->ruleIs(false);
		$this->policyService->expects($this->never())->method('isManaged');

		$this->assertFalse($this->guard->appliesTo($this->createMock(IUser::class)));
	}

	public function testNothingIsTouchedWhileTheRuleIsOff(): void {
		$this->ruleIs(false);
		$this->policyService->expects($this->never())->method('isManaged');
		$this->registry->expects($this->never())->method('getProviderStates');
		$this->jobList->expects($this->never())->method('has');

		$this->guard->enforce($this->createMock(IUser::class));
	}

	public function testUnmanagedUserKeepsTheirCodes(): void {
		$this->ruleIs(true);
		$this->policyService->method('isManaged')->willReturn(false);
		$this->registry->expects($this->never())->method('getProviderStates');
		$this->jobList->expects($this->never())->method('has');

		$this->guard->enforce($this->createMock(IUser::class));
	}

	public function testCodesOfAManagedUserAreRevoked(): void {
		$this->ruleIs(true);
		$user = $this->managedUser('alice', true);
		$providerManager = $this->providerManager(true);
		$this->logger->expects($this->never())->method('error');

		$this->guard->enforce($user);

		$this->assertSame([['backup_codes', $user]], $providerManager->calls);
	}

	public function testManagedUserWithoutCodesIsNotRevoked(): void {
		$this->ruleIs(true);
		$user = $this->managedUser('alice', false);
		$this->container->expects($this->never())->method('get');

		$this->guard->enforce($user);
	}

	public function testFailedRevocationIsLogged(): void {
		$this->ruleIs(true);
		$user = $this->managedUser('alice', true);
		$this->providerManager(false);
		$this->logger->expects($this->once())->method('error');

		$this->guard->enforce($user);
	}

	public function testUnavailableProviderManagerIsLogged(): void {
		$this->ruleIs(true);
		$user = $this->managedUser('alice', true);
		$this->container->method('get')->willThrowException(new RuntimeException('not found'));
		$this->logger->expects($this->once())->method('error');

		$this->guard->enforce($user);
	}

	public function testPendingReminderIsWithdrawn(): void {
		$this->ruleIs(true);
		$user = $this->managedUser('alice', false);
		$this->jobList->method('has')->with(self::REMINDER_JOB_CLASS, ['uid' => 'alice'])->willReturn(true);
		$this->jobList->expects($this->once())->method('remove')->with(self::REMINDER_JOB_CLASS, ['uid' => 'alice']);

		$notification = $this->createMock(INotification::class);
		$notification->expects($this->once())->method('setApp')->with('twofactor_backupcodes')->willReturnSelf();
		$notification->expects($this->once())->method('setUser')->with('alice')->willReturnSelf();
		$notification->expects($this->once())->method('setObject')->with('create', 'codes')->willReturnSelf();
		$this->notificationManager->method('createNotification')->willReturn($notification);
		$this->notificationManager->expects($this->once())->method('markProcessed')->with($notification);

		$this->guard->enforce($user);
	}

	public function testWithoutReminderNoNotificationIsTouched(): void {
		$this->ruleIs(true);
		$user = $this->managedUser('alice', false);
		$this->jobList->method('has')->willReturn(false);
		$this->jobList->expects($this->never())->method('remove');
		$this->notificationManager->expects($this->never())->method('markProcessed');

		$this->guard->enforce($user);
	}

	public function testEnforceAllCoversEveryManagedUser(): void {
		$this->ruleIs(true);
		$alice = $this->managedUser('alice', false);
		$bob = $this->createMock(IUser::class);
		$bob->method('getUID')->willReturn('bob');
		$this->policyService->method('listManagedUsers')->willReturn([$alice, $bob]);
		$this->jobList->expects($this->exactly(2))->method('has');

		$this->guard->enforceAll();
	}

	public function testEnforceAllDoesNothingWhileTheRuleIsOff(): void {
		$this->ruleIs(false);
		$this->policyService->expects($this->never())->method('listManagedUsers');

		$this->guard->enforceAll();
	}
}
