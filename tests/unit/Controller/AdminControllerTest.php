<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorOath\Tests\Unit\Controller;

use OCA\TwoFactorOath\Constants;
use OCA\TwoFactorOath\Controller\AdminController;
use OCA\TwoFactorOath\Db\IOtpSecretMapper;
use OCA\TwoFactorOath\Service\IOtpService;
use OCA\TwoFactorOath\Service\IPolicyService;
use OCA\TwoFactorOath\Service\ITotpImporter;
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\Authentication\TwoFactorAuth\IProvider;
use OCP\Authentication\TwoFactorAuth\IRegistry;
use OCP\Defaults;
use OCP\IAppConfig;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AdminControllerTest extends TestCase {
	private const TOTP_APP_ID = 'twofactor_totp';

	private IAppConfig&MockObject $appConfig;
	private IPolicyService&MockObject $policyService;
	private IOtpService&MockObject $otpService;
	private IUserManager&MockObject $userManager;
	private IRegistry&MockObject $registry;
	private IProvider&MockObject $provider;
	private ITotpImporter&MockObject $totpImporter;
	private IAppManager&MockObject $appManager;
	private AdminController $controller;

	protected function setUp(): void {
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->policyService = $this->createMock(IPolicyService::class);
		$this->otpService = $this->createMock(IOtpService::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->registry = $this->createMock(IRegistry::class);
		$this->provider = $this->createMock(IProvider::class);
		$this->totpImporter = $this->createMock(ITotpImporter::class);
		$this->appManager = $this->createMock(IAppManager::class);

		$this->controller = new AdminController(
			'twofactor_oath',
			$this->createMock(IRequest::class),
			$this->appConfig,
			$this->policyService,
			$this->otpService,
			$this->createMock(IOtpSecretMapper::class),
			$this->userManager,
			$this->registry,
			$this->provider,
			$this->createMock(Defaults::class),
			$this->totpImporter,
			$this->appManager,
		);
	}

	public function testImportFromTotpRejectedWhenTotpDisabled(): void {
		$this->appManager->method('isEnabledForAnyone')->with(self::TOTP_APP_ID)->willReturn(false);

		$response = $this->controller->importFromTotp();

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testImportFromTotpRunsTheImporter(): void {
		$this->appManager->method('isEnabledForAnyone')->willReturn(true);
		$this->totpImporter->method('import')->willReturn(['imported' => 2, 'skipped' => 1]);

		$response = $this->controller->importFromTotp();

		$this->assertSame(['imported' => 2, 'skipped' => 1], $response->getData());
	}

	public function testTotpStatusReportsCounts(): void {
		$this->appManager->method('isEnabledForAnyone')->willReturn(true);
		$this->totpImporter->method('available')->willReturn(3);
		$this->totpImporter->method('duplicateUserIds')->willReturn(['bob']);

		$response = $this->controller->totpStatus();

		$this->assertSame(['enabled' => true, 'importCount' => 3, 'duplicateUsers' => ['bob']], $response->getData());
	}

	public function testSetSecretLengthRejectsNonPreset(): void {
		$this->appConfig->expects($this->never())->method('setValueInt');

		$response = $this->controller->setSecretLength(999);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testSetSecretLengthStoresAPreset(): void {
		$this->appConfig->expects($this->once())->method('setValueInt');

		$response = $this->controller->setSecretLength(20);

		$this->assertSame(['length' => 20], $response->getData());
	}

	public function testSetManagedGroupsFiltersNonStrings(): void {
		$this->appConfig->expects($this->once())
			->method('setValueArray')
			->with('twofactor_oath', Constants::CONFIG_MANAGED_GROUPS, ['admins']);

		$response = $this->controller->setManagedGroups(['admins', 5, null]);

		$this->assertSame(['groups' => ['admins']], $response->getData());
	}

	public function testDisableUsersReportsUnknownUser(): void {
		$this->userManager->method('get')->with('ghost')->willReturn(null);

		$response = $this->controller->disableUsers(['ghost']);

		$this->assertSame([
			'results' => [['username' => 'ghost', 'status' => 'error', 'message' => 'unknown user']],
		], $response->getData());
	}

	public function testDisableUsersDisablesAManagedUser(): void {
		$user = $this->createMock(IUser::class);
		$this->userManager->method('get')->with('bob')->willReturn($user);
		$this->policyService->method('isManaged')->with($user)->willReturn(true);
		$this->otpService->expects($this->once())->method('disable')->with('bob');
		$this->registry->expects($this->once())->method('disableProviderFor')->with($this->provider, $user);

		$response = $this->controller->disableUsers(['bob']);

		$this->assertSame([
			'results' => [['username' => 'bob', 'status' => 'none']],
		], $response->getData());
	}

	public function testShowTokenReturnsNotFoundForUnknownUser(): void {
		$this->userManager->method('get')->with('ghost')->willReturn(null);

		$response = $this->controller->showToken('ghost');

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}
}
