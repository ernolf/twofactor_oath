<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorOath\Service;

use InvalidArgumentException;
use OCA\TwoFactorOath\AppInfo\Application;
use OCA\TwoFactorOath\Constants;
use OCA\TwoFactorOath\Db\IOtpSecretMapper;
use OCA\TwoFactorOath\Db\OtpSecret;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IAppConfig;
use OCP\IURLGenerator;
use OCP\Security\ICrypto;
use OTPHP\HOTP;
use OTPHP\OTPInterface;
use OTPHP\TOTP;
use Override;
use ParagonIE\ConstantTime\Base32;

/**
 * Wraps the otphp library: builds TOTP/HOTP objects from a stored configuration,
 * generates provisioning URIs and verifies codes (with HOTP counter resync and
 * basic TOTP replay protection).
 */
final class OtpService implements IOtpService {
	public function __construct(
		private readonly IOtpSecretMapper $mapper,
		private readonly IAppConfig $appConfig,
		private readonly IURLGenerator $urlGenerator,
		private readonly ICrypto $crypto,
		private readonly Ocra $ocra,
	) {
	}

	public function isValidAlgorithm(int $algorithm): bool {
		return array_key_exists($algorithm, Constants::ALGORITHM_DIGESTS);
	}

	public function isValidType(int $type): bool {
		return $type === Constants::TYPE_TOTP
			|| $type === Constants::TYPE_HOTP
			|| $type === Constants::TYPE_OCRA;
	}

	public function isValidDigits(int $digits): bool {
		return $digits >= Constants::MIN_DIGITS && $digits <= Constants::MAX_DIGITS;
	}

	public function isValidPeriod(int $period): bool {
		return in_array($period, Constants::PERIOD_VALUES, true);
	}

	/** Map a CSV type name (totp/hotp) to its integer code, or null if unknown. */
	#[Override]
	public function typeFromName(string $name): ?int {
		$code = array_search(strtolower($name), Constants::TYPE_NAMES, true);

		return $code === false ? null : $code;
	}

	/** Map a CSV algorithm name (sha1/sha256/sha512) to its code, or null if unknown. */
	#[Override]
	public function algorithmFromName(string $name): ?int {
		$code = array_search(strtolower($name), Constants::ALGORITHM_DIGESTS, true);

		return $code === false ? null : $code;
	}

	/** Validate a user-supplied Base32 secret (RFC 4648 alphabet, charset only). */
	public function isValidBase32(string $secret): bool {
		return $secret !== '' && preg_match('/^[A-Z2-7]+$/', strtoupper($secret)) === 1;
	}

	/**
	 * Whether a Base32 string length decodes to a whole number of bytes. Lengths
	 * with (length mod 8) of 1, 3 or 6 leave dangling bits and are rejected by
	 * strict importers (e.g. Google Authenticator, FreeOTP).
	 */
	public function isValidBase32Length(string $secret): bool {
		return !in_array(strlen($secret) % 8, [1, 3, 6], true);
	}

	/** The admin-configured secret length in bytes (key material) for generation. */
	public function getConfiguredSecretBytes(): int {
		$bytes = $this->appConfig->getValueInt(Application::APP_ID, Constants::CONFIG_SECRET_LENGTH, Constants::SECRET_BYTES_DEFAULT);

		return max(Constants::SECRET_BYTES_MIN, min(Constants::SECRET_BYTES_MAX, $bytes));
	}

	/**
	 * Generate a fresh random Base32 secret of the given (or configured) number of
	 * bytes. The whole-byte input always encodes to a cleanly decodable Base32
	 * string (no truncation), so it imports into any authenticator.
	 */
	public function generateSecret(?int $bytes = null): string {
		$bytes ??= $this->getConfiguredSecretBytes();

		return Base32::encodeUpperUnpadded(random_bytes($bytes));
	}

	/** The user's stored secret, or null if none exists. */
	#[Override]
	public function findByUserId(string $userId): ?OtpSecret {
		try {
			return $this->mapper->getByUserId($userId);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/** Whether the user has a fully enabled secret. */
	#[Override]
	public function hasEnabledSecret(string $userId): bool {
		try {
			return $this->mapper->getByUserId($userId)->isEnabled();
		} catch (DoesNotExistException) {
			return false;
		}
	}

	/**
	 * Create a fresh, not-yet-confirmed secret for the user, replacing any
	 * existing entry. With no extra arguments it produces a standard TOTP
	 * secret; advanced parameters and a custom Base32 secret may be supplied.
	 *
	 * @throws InvalidArgumentException on invalid parameters
	 */
	#[Override]
	public function createSecret(
		string $userId,
		int $type = Constants::DEFAULT_TYPE,
		int $algorithm = Constants::DEFAULT_ALGORITHM,
		int $digits = Constants::DEFAULT_DIGITS,
		int $period = Constants::DEFAULT_PERIOD,
		?string $customSecret = null,
		int $state = Constants::STATE_CREATED,
		bool $locked = false,
		int $counter = Constants::DEFAULT_COUNTER,
		int $epoch = Constants::DEFAULT_EPOCH,
		?string $suite = null,
		bool $trusted = false,
	): OtpSecret {
		if (!$this->isValidType($type)) {
			throw new InvalidArgumentException('Invalid OTP type');
		}
		if ($type === Constants::TYPE_OCRA) {
			// OCRA: the suite defines hash + digits; the other parameters are unused.
			// Validate by parsing (loose, RFC-engine level): parseSuite accepts the
			// supported hashes; we additionally require a sane digit count.
			if ($suite === null || $suite === '') {
				throw new InvalidArgumentException('OCRA suite is required');
			}
			$parsed = $this->ocra->parseSuite($suite);
			if (!$this->isValidDigits($parsed['digits'])) {
				throw new InvalidArgumentException('OCRA digit count is out of range');
			}
			$algorithm = $this->algorithmFromName($parsed['hash']) ?? Constants::DEFAULT_ALGORITHM;
			$digits = $parsed['digits'];
			$period = Constants::DEFAULT_PERIOD;
			$counter = Constants::DEFAULT_COUNTER;
			$epoch = Constants::DEFAULT_EPOCH;
		} else {
			$suite = null;
			if (!$this->isValidAlgorithm($algorithm)) {
				throw new InvalidArgumentException('Invalid algorithm');
			}
			if (!$this->isValidDigits($digits)) {
				throw new InvalidArgumentException('Number of digits is out of range');
			}
			if (!$this->isValidPeriod($period)) {
				throw new InvalidArgumentException('Period is out of range');
			}
			if ($counter < 0) {
				throw new InvalidArgumentException('Counter must not be negative');
			}
			if ($epoch < 0) {
				throw new InvalidArgumentException('Epoch must not be negative');
			}
		}

		$secret = $this->generateSecret();
		if ($customSecret !== null && $customSecret !== '') {
			$normalized = rtrim(strtoupper(str_replace(' ', '', $customSecret)), '=');
			if (!$this->isValidBase32($normalized)) {
				throw new InvalidArgumentException('Invalid Base32 secret (allowed: A-Z and 2-7)');
			}
			// Imported secrets ($trusted) already work in their source app, so skip the
			// stricter length checks that would otherwise reject short/odd-length keys.
			if (!$trusted) {
				if (!$this->isValidBase32Length($normalized)) {
					throw new InvalidArgumentException('Invalid Base32 length: this number of characters does not decode to whole bytes and will be rejected by authenticator apps. Use a length whose count mod 8 is not 1, 3 or 6.');
				}
				if (intdiv(strlen($normalized) * 5, 8) < Constants::SECRET_BYTES_MIN) {
					throw new InvalidArgumentException('Secret is too short (minimum ' . Constants::SECRET_BYTES_MIN . ' bytes / 128 bit)');
				}
			}
			$secret = $normalized;
		}

		$this->mapper->deleteByUserId($userId);

		$entity = new OtpSecret();
		$entity->setUserId($userId);
		$entity->setType($type);
		$entity->setSecret($this->crypto->encrypt($secret));
		$entity->setAlgorithm($algorithm);
		$entity->setDigits($digits);
		$entity->setPeriod($period);
		$entity->setCounter($counter);
		$entity->setEpoch($epoch);
		$entity->setState($state);
		$entity->setLocked($locked);
		$entity->setSuite($suite);
		$entity->setCreatedAt(time());

		return $this->mapper->insert($entity);
	}

	/** Confirm a created secret by verifying a code, then enable it. */
	#[Override]
	public function enable(string $userId, string $code): bool {
		try {
			$entity = $this->mapper->getByUserId($userId);
		} catch (DoesNotExistException) {
			return false;
		}
		if (!$this->verify($entity, $code)) {
			return false;
		}
		$entity->setState(Constants::STATE_ENABLED);
		$this->mapper->update($entity);

		return true;
	}

	/** Remove the user's secret (disable the provider for the user). */
	#[Override]
	public function disable(string $userId): void {
		$this->mapper->deleteByUserId($userId);
	}

	/** The plaintext Base32 secret of a stored token (decrypted from storage). */
	#[Override]
	public function decryptSecret(OtpSecret $config): string {
		return $this->crypto->decrypt($config->getSecret());
	}

	/** Build an otphp OTP object from a stored configuration. */
	public function build(OtpSecret $config): OTPInterface {
		$secret = $this->decryptSecret($config);
		if ($config->isHotp()) {
			$otp = HOTP::createFromSecret($secret);
			$otp->setCounter($config->getCounter());
		} else {
			$otp = TOTP::createFromSecret($secret);
			$otp->setPeriod($config->getPeriod());
			$otp->setEpoch($config->getEpoch());
		}
		$otp->setDigits($config->getDigits());
		$otp->setDigest(Constants::ALGORITHM_DIGESTS[$config->getAlgorithm()]);

		return $otp;
	}

	/** Build the otpauth:// URI for the QR code, optionally embedding a favicon. */
	#[Override]
	public function getProvisioningUri(OtpSecret $config, string $label, string $issuer, ?string $imageUrl = null): string {
		$otp = $this->build($config);
		$otp->setLabel($label);
		$otp->setIssuer($issuer);
		$image = ($imageUrl !== null && $imageUrl !== '') ? $imageUrl : $this->getFaviconUrl();
		if ($image !== '') {
			$otp->setParameter('image', $image);
		}

		return $otp->getProvisioningUri();
	}

	/**
	 * Absolute URL of the instance favicon for the otpauth `image` parameter
	 * (supported by some authenticator apps, e.g. FreeOTP/Aegis/ente Auth).
	 */
	private function getFaviconUrl(): string {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->linkToRoute('theming.Icon.getFavicon', ['app' => 'core']),
		);
	}

	/**
	 * Verify a one-time code.
	 *
	 * On success for HOTP the counter is advanced past the matched value and
	 * persisted (look-ahead resync); for TOTP the used time slice is recorded to
	 * prevent immediate replay.
	 */
	#[Override]
	public function verify(OtpSecret $config, string $code): bool {
		$code = trim($code);
		if ($code === '') {
			return false;
		}

		return $config->isHotp()
			? $this->verifyHotp($config, $code)
			: $this->verifyTotp($config, $code);
	}

	private function verifyTotp(OtpSecret $config, string $code): bool {
		$otp = $this->build($config);
		$period = max(1, $config->getPeriod());
		// otphp rejects a leeway >= period, so clamp the tolerance below it.
		$leeway = min(Constants::TOTP_LEEWAY_SECONDS, $period - 1);

		if (!$otp->verify($code, null, $leeway > 0 ? $leeway : null)) {
			return false;
		}

		// Replay protection: reject a time slice that was already used.
		$slice = intdiv(time(), $period);
		if ($config->getLastUsed() !== null && $slice <= $config->getLastUsed()) {
			return false;
		}

		$config->setLastUsed($slice);
		$this->mapper->update($config);

		return true;
	}

	private function verifyHotp(OtpSecret $config, string $code): bool {
		$otp = $this->build($config);
		$start = $config->getCounter();

		for ($c = $start; $c <= $start + Constants::HOTP_WINDOW; $c++) {
			if (hash_equals($otp->at($c), $code)) {
				$config->setCounter($c + 1);
				$config->setLastUsed($c);
				$this->mapper->update($config);

				return true;
			}
		}

		return false;
	}

	/**
	 * Re-synchronize a drifted HOTP counter from two consecutive codes
	 * (RFC 4226 §7.4): search a wide window for an index where at(i) == code1
	 * and at(i + 1) == code2, then advance the counter past both. Requiring two
	 * consecutive matches keeps the wide window safe against guessing.
	 */
	#[Override]
	public function resyncHotp(OtpSecret $config, string $code1, string $code2): bool {
		if (!$config->isHotp()) {
			return false;
		}
		$code1 = trim($code1);
		$code2 = trim($code2);
		if ($code1 === '' || $code2 === '') {
			return false;
		}

		$otp = $this->build($config);
		$start = $config->getCounter();
		for ($c = $start; $c <= $start + Constants::HOTP_RESYNC_WINDOW; $c++) {
			if (hash_equals($otp->at($c), $code1) && hash_equals($otp->at($c + 1), $code2)) {
				$config->setCounter($c + 2);
				$config->setLastUsed($c + 1);
				$this->mapper->update($config);

				return true;
			}
		}

		return false;
	}

	/** Resync the enabled HOTP token of a user from two consecutive codes. */
	#[Override]
	public function resyncHotpForUser(string $userId, string $code1, string $code2): bool {
		try {
			$entity = $this->mapper->getByUserId($userId);
		} catch (DoesNotExistException) {
			return false;
		}
		if (!$entity->isEnabled()) {
			return false;
		}

		return $this->resyncHotp($entity, $code1, $code2);
	}

	/** Generate a fresh random numeric challenge for an OCRA token (QN suites). */
	#[Override]
	public function generateOcraChallenge(OtpSecret $config): string {
		$parsed = $this->ocra->parseSuite((string)$config->getSuite());
		$challenge = '';
		for ($i = 0; $i < $parsed['qLength']; $i++) {
			$challenge .= (string)random_int(0, 9);
		}

		return $challenge;
	}

	/** Verify an OCRA challenge-response against the stored suite + secret. */
	#[Override]
	public function verifyOcra(OtpSecret $config, string $challenge, string $response): bool {
		if (!$config->isOcra() || $config->getSuite() === null) {
			return false;
		}
		$response = trim($response);
		if ($response === '') {
			return false;
		}
		// Ocra::generate expects the raw key, so decode the stored Base32 secret.
		$key = Base32::decodeUpper($this->decryptSecret($config));
		$expected = $this->ocra->generate($config->getSuite(), $key, $challenge);

		return hash_equals($expected, $response);
	}

	/** Confirm a created OCRA secret by verifying a challenge-response, then enable it. */
	#[Override]
	public function enableOcra(string $userId, string $challenge, string $response): bool {
		try {
			$entity = $this->mapper->getByUserId($userId);
		} catch (DoesNotExistException) {
			return false;
		}
		if (!$this->verifyOcra($entity, $challenge, $response)) {
			return false;
		}
		$entity->setState(Constants::STATE_ENABLED);
		$this->mapper->update($entity);

		return true;
	}
}
