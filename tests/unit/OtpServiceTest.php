<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorOath\Tests\Unit;

use InvalidArgumentException;
use OCA\TwoFactorOath\Constants;
use OCA\TwoFactorOath\Db\IOtpSecretMapper;
use OCA\TwoFactorOath\Db\OtpSecret;
use OCA\TwoFactorOath\Service\Ocra;
use OCA\TwoFactorOath\Service\OtpService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IAppConfig;
use OCP\IURLGenerator;
use OCP\Security\ICrypto;
use OTPHP\HOTP;
use OTPHP\TOTP;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for OtpService with fully mocked collaborators. ICrypto is stubbed as
 * the identity function so the stored secret round-trips to the real Base32 value,
 * and the real Ocra engine is used so OCRA verification computes genuine responses.
 */
final class OtpServiceTest extends TestCase {
	/** Base32 of the RFC 4226/6287 test key "12345678901234567890" (20 bytes). */
	private const RFC_SECRET_B32 = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

	private IOtpSecretMapper&MockObject $mapper;
	private IAppConfig&MockObject $appConfig;
	private IURLGenerator&MockObject $urlGenerator;
	private ICrypto&MockObject $crypto;
	private OtpService $service;

	protected function setUp(): void {
		$this->mapper = $this->createMock(IOtpSecretMapper::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->crypto = $this->createMock(ICrypto::class);
		// Identity crypto: the stored secret is the plaintext Base32 itself.
		$this->crypto->method('encrypt')->willReturnArgument(0);
		$this->crypto->method('decrypt')->willReturnArgument(0);

		$this->service = new OtpService(
			$this->mapper,
			$this->appConfig,
			$this->urlGenerator,
			$this->crypto,
			new Ocra(),
		);
	}

	// == Validation helpers ==

	public function testIsValidType(): void {
		$this->assertTrue($this->service->isValidType(Constants::TYPE_TOTP));
		$this->assertTrue($this->service->isValidType(Constants::TYPE_HOTP));
		$this->assertTrue($this->service->isValidType(Constants::TYPE_OCRA));
		$this->assertFalse($this->service->isValidType(0));
		$this->assertFalse($this->service->isValidType(99));
	}

	public function testIsValidAlgorithm(): void {
		$this->assertTrue($this->service->isValidAlgorithm(Constants::ALGO_SHA1));
		$this->assertTrue($this->service->isValidAlgorithm(Constants::ALGO_SHA512));
		$this->assertFalse($this->service->isValidAlgorithm(0));
		$this->assertFalse($this->service->isValidAlgorithm(6));
	}

	public function testIsValidDigits(): void {
		$this->assertTrue($this->service->isValidDigits(Constants::MIN_DIGITS));
		$this->assertTrue($this->service->isValidDigits(Constants::MAX_DIGITS));
		$this->assertFalse($this->service->isValidDigits(Constants::MIN_DIGITS - 1));
		$this->assertFalse($this->service->isValidDigits(Constants::MAX_DIGITS + 1));
	}

	public function testIsValidPeriod(): void {
		$this->assertTrue($this->service->isValidPeriod(30));
		$this->assertFalse($this->service->isValidPeriod(7));
	}

	public function testTypeFromName(): void {
		$this->assertSame(Constants::TYPE_TOTP, $this->service->typeFromName('totp'));
		$this->assertSame(Constants::TYPE_HOTP, $this->service->typeFromName('HOTP'));
		$this->assertNull($this->service->typeFromName('nope'));
	}

	public function testAlgorithmFromName(): void {
		$this->assertSame(Constants::ALGO_SHA1, $this->service->algorithmFromName('sha1'));
		$this->assertSame(Constants::ALGO_SHA256, $this->service->algorithmFromName('SHA256'));
		$this->assertNull($this->service->algorithmFromName('md5'));
	}

	public function testIsValidBase32(): void {
		$this->assertTrue($this->service->isValidBase32('JBSWY3DP'));
		$this->assertTrue($this->service->isValidBase32('jbswy3dp'));
		$this->assertFalse($this->service->isValidBase32(''));
		$this->assertFalse($this->service->isValidBase32('ABC8'));
		$this->assertFalse($this->service->isValidBase32('ABC!'));
	}

	public function testIsValidBase32Length(): void {
		$this->assertTrue($this->service->isValidBase32Length(str_repeat('A', 16)));
		$this->assertTrue($this->service->isValidBase32Length(str_repeat('A', 32)));
		$this->assertFalse($this->service->isValidBase32Length(str_repeat('A', 17))); // 17 % 8 = 1
		$this->assertFalse($this->service->isValidBase32Length(str_repeat('A', 19))); // 19 % 8 = 3
		$this->assertFalse($this->service->isValidBase32Length(str_repeat('A', 22))); // 22 % 8 = 6
	}

	// == Secret generation ==

	public function testGenerateSecretLengthAndCharset(): void {
		$secret = $this->service->generateSecret(20);
		// 20 bytes -> ceil(20 * 8 / 5) = 32 unpadded Base32 characters.
		$this->assertSame(32, strlen($secret));
		$this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
		$this->assertNotSame($secret, $this->service->generateSecret(20));
	}

	public function testGetConfiguredSecretBytesClamps(): void {
		$this->appConfig->method('getValueInt')->willReturn(5);
		$this->assertSame(Constants::SECRET_BYTES_MIN, $this->service->getConfiguredSecretBytes());
	}

	public function testGetConfiguredSecretBytesClampsHigh(): void {
		$this->appConfig->method('getValueInt')->willReturn(999);
		$this->assertSame(Constants::SECRET_BYTES_MAX, $this->service->getConfiguredSecretBytes());
	}

	public function testGetConfiguredSecretBytesPassesThrough(): void {
		$this->appConfig->method('getValueInt')->willReturn(40);
		$this->assertSame(40, $this->service->getConfiguredSecretBytes());
	}

	// == createSecret: validation ==

	public function testCreateSecretRejectsInvalidType(): void {
		$this->mapper->expects($this->never())->method('insert');
		$this->expectException(InvalidArgumentException::class);
		$this->service->createSecret('user', type: 42);
	}

	public function testCreateSecretRejectsInvalidAlgorithm(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->service->createSecret('user', type: Constants::TYPE_TOTP, algorithm: 99);
	}

	public function testCreateSecretRejectsInvalidDigits(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->service->createSecret('user', digits: 3);
	}

	public function testCreateSecretRejectsInvalidPeriod(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->service->createSecret('user', period: 7);
	}

	public function testCreateSecretRejectsNegativeCounter(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->service->createSecret('user', type: Constants::TYPE_HOTP, counter: -1);
	}

	public function testCreateSecretRejectsOcraWithoutSuite(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->service->createSecret('user', type: Constants::TYPE_OCRA);
	}

	public function testCreateSecretRejectsBadCustomSecretCharset(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->service->createSecret('user', customSecret: 'NOT!BASE32');
	}

	public function testCreateSecretRejectsCustomSecretBadLength(): void {
		// 9 Base32 chars: valid charset but 9 % 8 = 1 (dangling bits).
		$this->expectException(InvalidArgumentException::class);
		$this->service->createSecret('user', customSecret: 'JBSWY3DPE');
	}

	public function testCreateSecretRejectsTooShortCustomSecret(): void {
		// 8 chars = 5 bytes, below the 16-byte minimum.
		$this->expectException(InvalidArgumentException::class);
		$this->service->createSecret('user', customSecret: 'JBSWY3DP');
	}

	// == createSecret: happy paths ==

	public function testCreateSecretStandardTotp(): void {
		$this->mapper->expects($this->once())->method('deleteByUserId')->with('alice');
		$this->mapper->expects($this->once())->method('insert')->willReturnArgument(0);

		$entity = $this->service->createSecret('alice');

		$this->assertSame('alice', $entity->getUserId());
		$this->assertSame(Constants::TYPE_TOTP, $entity->getType());
		$this->assertSame(Constants::DEFAULT_ALGORITHM, $entity->getAlgorithm());
		$this->assertSame(Constants::DEFAULT_DIGITS, $entity->getDigits());
		$this->assertSame(Constants::STATE_CREATED, $entity->getState());
		$this->assertNull($entity->getSuite());
		// Identity-encrypted: the stored secret is a clean Base32 string.
		$this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $entity->getSecret());
	}

	public function testCreateSecretWithCustomSecretIsNormalized(): void {
		$this->mapper->method('deleteByUserId');
		$this->mapper->expects($this->once())->method('insert')->willReturnArgument(0);

		// Lowercase + spaces + padding must normalise to clean upper-case Base32.
		$entity = $this->service->createSecret('bob', customSecret: 'gezd gnbv gy3t qojq gezd gnbv gy3t qojq');

		$this->assertSame(self::RFC_SECRET_B32, $entity->getSecret());
	}

	public function testCreateSecretOcraDerivesAlgorithmAndDigitsFromSuite(): void {
		$this->mapper->method('deleteByUserId');
		$this->mapper->expects($this->once())->method('insert')->willReturnArgument(0);

		$entity = $this->service->createSecret('carol', type: Constants::TYPE_OCRA, suite: Constants::DEFAULT_OCRA_SUITE);

		$this->assertSame(Constants::TYPE_OCRA, $entity->getType());
		$this->assertSame(Constants::DEFAULT_OCRA_SUITE, $entity->getSuite());
		$this->assertSame(Constants::ALGO_SHA1, $entity->getAlgorithm());
		$this->assertSame(6, $entity->getDigits());
	}

	// == build + verify ==

	public function testBuildHotpMatchesRfc4226Vector(): void {
		$entity = $this->entity(Constants::TYPE_HOTP, self::RFC_SECRET_B32, ['counter' => 0]);
		// RFC 4226 Appendix D: HOTP-SHA1-6 with the standard key, counter 0.
		$this->assertSame('755224', $this->service->build($entity)->at(0));
	}

	public function testVerifyHotpAcceptsAndAdvancesCounter(): void {
		$entity = $this->entity(Constants::TYPE_HOTP, self::RFC_SECRET_B32, ['counter' => 0]);
		$this->mapper->expects($this->once())->method('update');

		$this->assertTrue($this->service->verify($entity, '755224'));
		$this->assertSame(1, $entity->getCounter());
		$this->assertSame(0, $entity->getLastUsed());
	}

	public function testVerifyHotpLookAheadWindow(): void {
		$entity = $this->entity(Constants::TYPE_HOTP, self::RFC_SECRET_B32, ['counter' => 0]);
		$code = $this->hotp(self::RFC_SECRET_B32)->at(3);
		$this->mapper->expects($this->once())->method('update');

		$this->assertTrue($this->service->verify($entity, $code));
		$this->assertSame(4, $entity->getCounter());
	}

	public function testVerifyHotpRejectsWrongCode(): void {
		$entity = $this->entity(Constants::TYPE_HOTP, self::RFC_SECRET_B32, ['counter' => 0]);
		$this->mapper->expects($this->never())->method('update');

		$this->assertFalse($this->service->verify($entity, '000000'));
		$this->assertSame(0, $entity->getCounter());
	}

	public function testVerifyTotpAcceptsCurrentCodeOnce(): void {
		$entity = $this->entity(Constants::TYPE_TOTP, self::RFC_SECRET_B32);
		$code = $this->totp(self::RFC_SECRET_B32)->now();
		$this->mapper->expects($this->once())->method('update');

		$this->assertTrue($this->service->verify($entity, $code));
		$this->assertNotNull($entity->getLastUsed());
	}

	public function testVerifyTotpRejectsReplayOfSameSlice(): void {
		$slice = intdiv(time(), 30);
		$entity = $this->entity(Constants::TYPE_TOTP, self::RFC_SECRET_B32, ['lastUsed' => $slice]);
		$code = $this->totp(self::RFC_SECRET_B32)->now();
		$this->mapper->expects($this->never())->method('update');

		$this->assertFalse($this->service->verify($entity, $code));
	}

	public function testVerifyRejectsEmptyCode(): void {
		$entity = $this->entity(Constants::TYPE_TOTP, self::RFC_SECRET_B32);
		$this->assertFalse($this->service->verify($entity, '   '));
	}

	// == HOTP resynchronisation ==

	public function testResyncHotpFromTwoConsecutiveCodes(): void {
		$entity = $this->entity(Constants::TYPE_HOTP, self::RFC_SECRET_B32, ['counter' => 0]);
		$otp = $this->hotp(self::RFC_SECRET_B32);
		$this->mapper->expects($this->once())->method('update');

		$this->assertTrue($this->service->resyncHotp($entity, $otp->at(5), $otp->at(6)));
		$this->assertSame(7, $entity->getCounter());
		$this->assertSame(6, $entity->getLastUsed());
	}

	public function testResyncHotpRejectsNonConsecutiveCodes(): void {
		$entity = $this->entity(Constants::TYPE_HOTP, self::RFC_SECRET_B32, ['counter' => 0]);
		$otp = $this->hotp(self::RFC_SECRET_B32);
		$this->mapper->expects($this->never())->method('update');

		$this->assertFalse($this->service->resyncHotp($entity, $otp->at(5), $otp->at(7)));
	}

	public function testResyncHotpRejectsNonHotpToken(): void {
		$entity = $this->entity(Constants::TYPE_TOTP, self::RFC_SECRET_B32);
		$this->assertFalse($this->service->resyncHotp($entity, '111111', '222222'));
	}

	// == OCRA ==

	public function testVerifyOcraAcceptsRfc6287Vector(): void {
		$entity = $this->entity(Constants::TYPE_OCRA, self::RFC_SECRET_B32, ['suite' => 'OCRA-1:HOTP-SHA1-6:QN08']);
		// RFC 6287 Appendix C: challenge "00000000" -> response "237653".
		$this->assertTrue($this->service->verifyOcra($entity, '00000000', '237653'));
	}

	public function testVerifyOcraRejectsWrongResponse(): void {
		$entity = $this->entity(Constants::TYPE_OCRA, self::RFC_SECRET_B32, ['suite' => 'OCRA-1:HOTP-SHA1-6:QN08']);
		$this->assertFalse($this->service->verifyOcra($entity, '00000000', '000000'));
	}

	public function testVerifyOcraRejectsNonOcraToken(): void {
		$entity = $this->entity(Constants::TYPE_TOTP, self::RFC_SECRET_B32);
		$this->assertFalse($this->service->verifyOcra($entity, '00000000', '237653'));
	}

	public function testGenerateOcraChallengeHasSuiteLength(): void {
		$entity = $this->entity(Constants::TYPE_OCRA, self::RFC_SECRET_B32, ['suite' => 'OCRA-1:HOTP-SHA1-6:QN08']);
		$challenge = $this->service->generateOcraChallenge($entity);
		$this->assertSame(8, strlen($challenge));
		$this->assertMatchesRegularExpression('/^[0-9]+$/', $challenge);
	}

	// == Mapper-backed flows ==

	public function testEnableConfirmsAndStoresEnabledState(): void {
		$entity = $this->entity(Constants::TYPE_HOTP, self::RFC_SECRET_B32, ['counter' => 0]);
		$this->mapper->method('getByUserId')->willReturn($entity);
		$this->mapper->expects($this->atLeastOnce())->method('update');

		$this->assertTrue($this->service->enable('alice', '755224'));
		$this->assertSame(Constants::STATE_ENABLED, $entity->getState());
	}

	public function testEnableFailsForWrongCode(): void {
		$entity = $this->entity(Constants::TYPE_HOTP, self::RFC_SECRET_B32, ['counter' => 0]);
		$this->mapper->method('getByUserId')->willReturn($entity);

		$this->assertFalse($this->service->enable('alice', '000000'));
		$this->assertSame(Constants::STATE_CREATED, $entity->getState());
	}

	public function testEnableFailsWhenNoSecret(): void {
		$this->mapper->method('getByUserId')->willThrowException(new DoesNotExistException('none'));
		$this->assertFalse($this->service->enable('ghost', '755224'));
	}

	public function testEnableOcraConfirmsToken(): void {
		$entity = $this->entity(Constants::TYPE_OCRA, self::RFC_SECRET_B32, ['suite' => 'OCRA-1:HOTP-SHA1-6:QN08']);
		$this->mapper->method('getByUserId')->willReturn($entity);
		$this->mapper->expects($this->once())->method('update');

		$this->assertTrue($this->service->enableOcra('carol', '00000000', '237653'));
		$this->assertSame(Constants::STATE_ENABLED, $entity->getState());
	}

	public function testDisableDeletesSecret(): void {
		$this->mapper->expects($this->once())->method('deleteByUserId')->with('alice');
		$this->service->disable('alice');
	}

	public function testHasEnabledSecret(): void {
		$enabled = $this->entity(Constants::TYPE_TOTP, self::RFC_SECRET_B32, ['state' => Constants::STATE_ENABLED]);
		$this->mapper->method('getByUserId')->willReturn($enabled);
		$this->assertTrue($this->service->hasEnabledSecret('alice'));
	}

	public function testHasEnabledSecretFalseForCreatedState(): void {
		$created = $this->entity(Constants::TYPE_TOTP, self::RFC_SECRET_B32);
		$this->mapper->method('getByUserId')->willReturn($created);
		$this->assertFalse($this->service->hasEnabledSecret('alice'));
	}

	public function testFindByUserIdReturnsNullWhenMissing(): void {
		$this->mapper->method('getByUserId')->willThrowException(new DoesNotExistException('none'));
		$this->assertNull($this->service->findByUserId('ghost'));
	}

	// == provisioning URI ==

	public function testProvisioningUriContainsIssuerAndImage(): void {
		$this->urlGenerator->method('linkToRoute')->willReturn('/index.php/apps/theming/favicon');
		$this->urlGenerator->method('getAbsoluteURL')->willReturnCallback(
			static fn (string $path): string => 'https://cloud.example' . $path,
		);
		$entity = $this->entity(Constants::TYPE_TOTP, self::RFC_SECRET_B32);

		$uri = $this->service->getProvisioningUri($entity, 'alice@cloud.example', 'Cloud');

		$this->assertStringStartsWith('otpauth://totp/', $uri);
		$this->assertStringContainsString('issuer=Cloud', $uri);
		$this->assertStringContainsString('image=', $uri);
	}

	public function testProvisioningUriUsesExplicitImageUrl(): void {
		$entity = $this->entity(Constants::TYPE_TOTP, self::RFC_SECRET_B32);
		$uri = $this->service->getProvisioningUri($entity, 'alice', 'Cloud', 'https://x/icon.png');
		$this->assertStringContainsString('image=', $uri);
	}

	// == helpers ==

	/**
	 * @param array{userId?: string, algorithm?: int, digits?: int, period?: int,
	 *     counter?: int, epoch?: int, state?: int, lastUsed?: ?int, suite?: ?string} $o
	 */
	private function entity(int $type, string $base32, array $o = []): OtpSecret {
		$e = new OtpSecret();
		$e->setUserId($o['userId'] ?? 'user');
		$e->setType($type);
		$e->setSecret($base32);
		$e->setAlgorithm($o['algorithm'] ?? Constants::ALGO_SHA1);
		$e->setDigits($o['digits'] ?? 6);
		$e->setPeriod($o['period'] ?? 30);
		$e->setCounter($o['counter'] ?? 0);
		$e->setEpoch($o['epoch'] ?? 0);
		$e->setState($o['state'] ?? Constants::STATE_CREATED);
		$e->setLocked(false);
		$e->setLastUsed($o['lastUsed'] ?? null);
		$e->setSuite($o['suite'] ?? null);
		$e->setCreatedAt(0);

		return $e;
	}

	private function hotp(string $base32): HOTP {
		$otp = HOTP::createFromSecret($base32);
		$otp->setDigits(6);
		$otp->setDigest('sha1');

		return $otp;
	}

	private function totp(string $base32): TOTP {
		$otp = TOTP::createFromSecret($base32);
		$otp->setPeriod(30);
		$otp->setEpoch(0);
		$otp->setDigits(6);
		$otp->setDigest('sha1');

		return $otp;
	}
}
