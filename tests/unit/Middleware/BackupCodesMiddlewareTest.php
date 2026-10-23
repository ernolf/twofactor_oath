<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorBackupCodes\Controller {
	use OCP\AppFramework\Controller;

	// twofactor_backupcodes is not loaded in every test run; the middleware only needs the class name.
	if (!class_exists(SettingsController::class)) {
		abstract class SettingsController extends Controller {
		}
	}
}

namespace OCA\TwoFactorOath\Tests\Unit\Middleware {
	use Exception;
	use OCA\TwoFactorBackupCodes\Controller\SettingsController;
	use OCA\TwoFactorOath\Middleware\BackupCodesForbiddenException;
	use OCA\TwoFactorOath\Middleware\BackupCodesMiddleware;
	use OCA\TwoFactorOath\Service\IBackupCodesGuard;
	use OCP\AppFramework\Controller;
	use OCP\AppFramework\Http;
	use OCP\AppFramework\Http\JSONResponse;
	use OCP\IUser;
	use OCP\IUserSession;
	use PHPUnit\Framework\MockObject\MockObject;
	use PHPUnit\Framework\TestCase;

	/**
	 * Unit tests for refusing backup code generation. Whether the rule applies to
	 * a user is the guard's decision, so the guard is mocked.
	 */
	final class BackupCodesMiddlewareTest extends TestCase {
		private IBackupCodesGuard&MockObject $guard;
		private IUserSession&MockObject $userSession;
		private IUser&MockObject $user;
		private BackupCodesMiddleware $middleware;

		protected function setUp(): void {
			$this->guard = $this->createMock(IBackupCodesGuard::class);
			$this->userSession = $this->createMock(IUserSession::class);
			$this->user = $this->createMock(IUser::class);
			$this->middleware = new BackupCodesMiddleware($this->guard, $this->userSession);
		}

		private function backupCodesController(): Controller {
			return $this->createMock(SettingsController::class);
		}

		public function testGeneratingCodesIsRefusedWhenTheRuleApplies(): void {
			$this->userSession->method('getUser')->willReturn($this->user);
			$this->guard->method('appliesTo')->with($this->user)->willReturn(true);

			$this->expectException(BackupCodesForbiddenException::class);

			$this->middleware->beforeController($this->backupCodesController(), 'createCodes');
		}

		public function testGeneratingCodesIsAllowedWhenTheRuleDoesNotApply(): void {
			$this->userSession->method('getUser')->willReturn($this->user);
			$this->guard->method('appliesTo')->with($this->user)->willReturn(false);

			$this->middleware->beforeController($this->backupCodesController(), 'createCodes');

			$this->addToAssertionCount(1);
		}

		public function testRequestWithoutUserIsLeftToTheController(): void {
			$this->userSession->method('getUser')->willReturn(null);
			$this->guard->expects($this->never())->method('appliesTo');

			$this->middleware->beforeController($this->backupCodesController(), 'createCodes');
		}

		public function testOtherMethodOfTheControllerIsIgnored(): void {
			$this->guard->expects($this->never())->method('appliesTo');

			$this->middleware->beforeController($this->backupCodesController(), 'index');
		}

		public function testOtherControllerIsIgnored(): void {
			$this->guard->expects($this->never())->method('appliesTo');

			$this->middleware->beforeController($this->createMock(Controller::class), 'createCodes');
		}

		public function testRefusalBecomesForbiddenResponse(): void {
			$response = $this->middleware->afterException(
				$this->backupCodesController(),
				'createCodes',
				new BackupCodesForbiddenException('Backup codes are disabled for this account'),
			);

			$this->assertInstanceOf(JSONResponse::class, $response);
			$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		}

		public function testForeignExceptionIsRethrown(): void {
			$exception = new Exception('unrelated');

			$this->expectExceptionObject($exception);

			$this->middleware->afterException($this->createMock(Controller::class), 'index', $exception);
		}
	}
}
