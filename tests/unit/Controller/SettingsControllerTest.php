<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorOath\Tests\Unit\Controller;

use InvalidArgumentException;
use OCA\TwoFactorOath\Constants;
use OCA\TwoFactorOath\Controller\SettingsController;
use OCA\TwoFactorOath\Service\IOtpService;
use OCA\TwoFactorOath\Service\IPolicyService;
use OCP\AppFramework\Http;
use OCP\Authentication\TwoFactorAuth\IProvider;
use OCP\Authentication\TwoFactorAuth\IRegistry;
use OCP\Defaults;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SettingsControllerTest extends TestCase {
	private IOtpService&MockObject $otpService;
	private IPolicyService&MockObject $policyService;
	private IRegistry&MockObject $registry;
	private IProvider&MockObject $provider;
	private IUser&MockObject $user;
	private SettingsController $controller;

	protected function setUp(): void {
		$this->otpService = $this->createMock(IOtpService::class);
		$this->policyService = $this->createMock(IPolicyService::class);
		$this->registry = $this->createMock(IRegistry::class);
		$this->provider = $this->createMock(IProvider::class);

		$this->user = $this->createMock(IUser::class);
		$this->user->method('getUID')->willReturn('alice');
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($this->user);

		$this->controller = new SettingsController(
			'twofactor_oath',
			$this->createMock(IRequest::class),
			$userSession,
			$this->createMock(ISession::class),
			$this->otpService,
			$this->policyService,
			$this->registry,
			$this->provider,
			$this->createMock(Defaults::class),
		);
	}

	public function testStateReflectsEnabledToken(): void {
		$this->otpService->method('hasEnabledSecret')->with('alice')->willReturn(true);

		$response = $this->controller->state();

		$this->assertSame(['state' => Constants::STATE_ENABLED], $response->getData());
	}

	public function testEnableIsForbiddenForManagedUser(): void {
		$this->policyService->method('isManaged')->with($this->user)->willReturn(true);
		$this->otpService->expects($this->never())->method('disable');

		$response = $this->controller->enable(Constants::STATE_DISABLED);

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
	}

	public function testEnableWithDisabledStateRemovesTheSecret(): void {
		$this->policyService->method('isManaged')->willReturn(false);
		$this->otpService->expects($this->once())->method('disable')->with('alice');
		$this->registry->expects($this->once())->method('disableProviderFor')->with($this->provider, $this->user);

		$response = $this->controller->enable(Constants::STATE_DISABLED);

		$this->assertSame(['state' => Constants::STATE_DISABLED], $response->getData());
	}

	public function testEnableRejectsAnInvalidState(): void {
		$this->policyService->method('isManaged')->willReturn(false);

		$this->expectException(InvalidArgumentException::class);
		$this->controller->enable(99);
	}

	public function testConfigReturnsNullWithoutAToken(): void {
		$this->otpService->method('findByUserId')->with('alice')->willReturn(null);

		$response = $this->controller->config();

		$this->assertSame(['config' => null], $response->getData());
	}

	public function testDeactivateIsForbiddenForManagedUser(): void {
		$this->policyService->method('isManaged')->willReturn(true);

		$response = $this->controller->deactivate();

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
	}

	public function testShowReturnsNotFoundWithoutAToken(): void {
		$this->policyService->method('isManaged')->willReturn(false);
		$this->otpService->method('findByUserId')->willReturn(null);

		$response = $this->controller->show();

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}
}
