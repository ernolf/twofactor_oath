<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorOath\Tests\Unit\Provider;

use OCA\TwoFactorOath\Constants;
use OCA\TwoFactorOath\Db\IOtpSecretMapper;
use OCA\TwoFactorOath\Db\OtpSecret;
use OCA\TwoFactorOath\Provider\OtpProvider;
use OCA\TwoFactorOath\Service\IOtpService;
use OCA\TwoFactorOath\Service\IPolicyService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Services\IInitialState;
use OCP\IL10N;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Template\ITemplateManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class OtpProviderTest extends TestCase {
	private IOtpSecretMapper&MockObject $mapper;
	private IOtpService&MockObject $otpService;
	private IUser&MockObject $user;
	private OtpProvider $provider;

	protected function setUp(): void {
		$this->mapper = $this->createMock(IOtpSecretMapper::class);
		$this->otpService = $this->createMock(IOtpService::class);
		$this->user = $this->createMock(IUser::class);
		$this->user->method('getUID')->willReturn('alice');

		$this->provider = new OtpProvider(
			$this->mapper,
			$this->otpService,
			$this->createMock(IPolicyService::class),
			$this->createMock(ITemplateManager::class),
			$this->createMock(IInitialState::class),
			$this->createMock(IL10N::class),
			$this->createMock(IRequest::class),
			$this->createMock(ISession::class),
			$this->createMock(IURLGenerator::class),
		);
	}

	private function enabledTotp(): OtpSecret {
		$entity = new OtpSecret();
		$entity->setType(Constants::TYPE_TOTP);
		$entity->setState(Constants::STATE_ENABLED);

		return $entity;
	}

	public function testGetIdIsOath(): void {
		$this->assertSame('oath', $this->provider->getId());
	}

	public function testGetDisplayName(): void {
		$this->assertSame('OATH (TOTP/HOTP/OCRA)', $this->provider->getDisplayName());
	}

	public function testEnabledForUserWithAnEnabledSecret(): void {
		$this->mapper->method('getByUserId')->with('alice')->willReturn($this->enabledTotp());

		$this->assertTrue($this->provider->isTwoFactorAuthEnabledForUser($this->user));
	}

	public function testNotEnabledForUserWithoutSecret(): void {
		$this->mapper->method('getByUserId')->willThrowException(new DoesNotExistException('none'));

		$this->assertFalse($this->provider->isTwoFactorAuthEnabledForUser($this->user));
	}

	public function testDisableForRemovesTheSecret(): void {
		$this->otpService->expects($this->once())->method('disable')->with('alice');

		$this->provider->disableFor($this->user);
	}

	public function testVerifyChallengeFailsWithoutSecret(): void {
		$this->mapper->method('getByUserId')->willThrowException(new DoesNotExistException('none'));

		$this->assertFalse($this->provider->verifyChallenge($this->user, '123456'));
	}

	public function testVerifyChallengeDelegatesTotpToTheService(): void {
		$entity = $this->enabledTotp();
		$this->mapper->method('getByUserId')->willReturn($entity);
		$this->otpService->method('verify')->with($entity, '123456')->willReturn(true);

		$this->assertTrue($this->provider->verifyChallenge($this->user, '123456'));
	}
}
