<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorOath\Service;

use InvalidArgumentException;
use OCA\TwoFactorOath\Constants;
use OCA\TwoFactorOath\Db\OtpSecret;

/**
 * OTP business logic the controllers and provider depend on. Lets them be
 * unit-tested with a mock instead of the concrete OtpService.
 */
interface IOtpService {
	/** Map a CSV type name (totp/hotp) to its integer code, or null if unknown. */
	public function typeFromName(string $name): ?int;

	/** Map a CSV algorithm name (sha1/sha256/sha512) to its code, or null if unknown. */
	public function algorithmFromName(string $name): ?int;

	/** The user's stored secret, or null if none exists. */
	public function findByUserId(string $userId): ?OtpSecret;

	/** Whether the user has a fully enabled secret. */
	public function hasEnabledSecret(string $userId): bool;

	/**
	 * Create a fresh, not-yet-confirmed secret for the user, replacing any
	 * existing entry.
	 *
	 * @throws InvalidArgumentException on invalid parameters
	 */
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
	): OtpSecret;

	/** Confirm a created secret by verifying a code, then enable it. */
	public function enable(string $userId, string $code): bool;

	/** Remove the user's secret (disable the provider for the user). */
	public function disable(string $userId): void;

	/** The plaintext Base32 secret of a stored token (decrypted from storage). */
	public function decryptSecret(OtpSecret $config): string;

	/** Build the otpauth:// URI for the QR code, optionally embedding a favicon. */
	public function getProvisioningUri(OtpSecret $config, string $label, string $issuer, ?string $imageUrl = null): string;

	/** Verify a one-time code (advances the HOTP counter / records the TOTP slice). */
	public function verify(OtpSecret $config, string $code): bool;

	/** Re-synchronize a drifted HOTP counter from two consecutive codes. */
	public function resyncHotp(OtpSecret $config, string $code1, string $code2): bool;

	/** Resync the enabled HOTP token of a user from two consecutive codes. */
	public function resyncHotpForUser(string $userId, string $code1, string $code2): bool;

	/** Generate a fresh random numeric challenge for an OCRA token (QN suites). */
	public function generateOcraChallenge(OtpSecret $config): string;

	/** Verify an OCRA challenge-response against the stored suite + secret. */
	public function verifyOcra(OtpSecret $config, string $challenge, string $response): bool;

	/** Confirm a created OCRA secret by verifying a challenge-response, then enable it. */
	public function enableOcra(string $userId, string $challenge, string $response): bool;
}
