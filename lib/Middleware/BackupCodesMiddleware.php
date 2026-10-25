<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorOath\Middleware;

use Exception;
use OCA\TwoFactorOath\Service\IBackupCodesGuard;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Middleware;
use OCP\IUserSession;
use Override;

/**
 * Registered as a global middleware, so it also runs in front of the controllers
 * of twofactor_backupcodes. It refuses the request that generates backup codes
 * for a user the backup-code rule applies to, so no codes are created at all.
 */
final class BackupCodesMiddleware extends Middleware {
	/**
	 * Controller of twofactor_backupcodes behind its `settings#createCodes` route.
	 * Named, not imported, so the app does not depend on another app's classes.
	 */
	private const CONTROLLER_CLASS = 'OCA\\TwoFactorBackupCodes\\Controller\\SettingsController';
	private const CREATE_METHOD = 'createCodes';

	public function __construct(
		private readonly IBackupCodesGuard $guard,
		private readonly IUserSession $userSession,
	) {
	}

	#[Override]
	public function beforeController(Controller $controller, string $methodName): void {
		if (!is_a($controller, self::CONTROLLER_CLASS) || $methodName !== self::CREATE_METHOD) {
			return;
		}

		$user = $this->userSession->getUser();
		if ($user !== null && $this->guard->appliesTo($user)) {
			throw new BackupCodesForbiddenException('Backup codes are disabled for this account');
		}
	}

	#[Override]
	public function afterException(Controller $controller, string $methodName, Exception $exception): Response {
		if ($exception instanceof BackupCodesForbiddenException) {
			return new JSONResponse(['message' => $exception->getMessage()], Http::STATUS_FORBIDDEN);
		}

		throw $exception;
	}
}
