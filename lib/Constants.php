<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorOath;

/**
 * Central registry of all app-wide constants: OTP types, enrollment states,
 * hash algorithms, defaults, accepted ranges and verification tuning.
 *
 * Everything is kept in this single class so the domain model is documented in
 * one place instead of being scattered across entity, service and controller.
 */
final class Constants {
	// OTP type (stored as an integer code).
	public const TYPE_TOTP = 1;
	public const TYPE_HOTP = 2;
	public const TYPE_OCRA = 3;

	// Per-user enrollment state. STATE_DISABLED is not persisted (no row =
	// disabled); it only lets the controller and frontend speak one 0/1/2
	// state machine.
	public const STATE_DISABLED = 0;
	public const STATE_CREATED = 1;
	public const STATE_ENABLED = 2;

	// Hash algorithm (stored as an integer code, mapped to the otphp digest
	// name in ALGORITHM_DIGESTS).
	public const ALGO_SHA1 = 1;
	public const ALGO_SHA224 = 2;
	public const ALGO_SHA256 = 3;
	public const ALGO_SHA384 = 4;
	public const ALGO_SHA512 = 5;

	/** @var array<int, string> algorithm code => otphp digest name */
	public const ALGORITHM_DIGESTS = [
		self::ALGO_SHA1 => 'sha1',
		self::ALGO_SHA224 => 'sha224',
		self::ALGO_SHA256 => 'sha256',
		self::ALGO_SHA384 => 'sha384',
		self::ALGO_SHA512 => 'sha512',
	];

	/** @var array<int, string> type code => name (for CSV import/export) */
	public const TYPE_NAMES = [
		self::TYPE_TOTP => 'totp',
		self::TYPE_HOTP => 'hotp',
		self::TYPE_OCRA => 'ocra',
	];

	// Defaults for the standard (non-advanced) enrollment.
	public const DEFAULT_TYPE = self::TYPE_TOTP;
	public const DEFAULT_ALGORITHM = self::ALGO_SHA1;
	public const DEFAULT_DIGITS = 6;
	public const DEFAULT_PERIOD = 30;
	public const DEFAULT_COUNTER = 0;
	public const DEFAULT_EPOCH = 0;

	// Accepted ranges for advanced settings.
	public const MIN_DIGITS = 4;
	public const MAX_DIGITS = 10;

	/** Allowed TOTP periods in seconds (client-compatible steps, e.g. FreeOTP). */
	public const PERIOD_VALUES = [15, 20, 25, 30, 45, 60, 90, 120, 180, 240, 300, 600];

	// Secret length as bytes of key material (NOT Base32 characters): generating a
	// whole number of bytes guarantees a Base32 string that decodes cleanly and
	// imports everywhere. RFC 4226 R6: >= 128 bit (16 bytes), 160 bit recommended.
	public const SECRET_BYTES_MIN = 16;       // 128 bit
	public const SECRET_BYTES_DEFAULT = 20;   // 160 bit (RFC recommendation)
	public const SECRET_BYTES_MAX = 80;       // 640 bit
	/** Preset strengths (bytes) offered for the admin default length. */
	public const SECRET_PRESET_BYTES = [16, 20, 40, 60, 80];

	/** App-config keys. */
	public const CONFIG_SECRET_LENGTH = 'secret_length';
	public const CONFIG_MANAGED_GROUPS = 'managed_groups';
	public const CONFIG_EXCLUDED_GROUPS = 'excluded_groups';

	// Verification tuning.
	public const HOTP_WINDOW = 10;          // HOTP look-ahead window during normal verification (RFC 4226)
	public const HOTP_RESYNC_WINDOW = 1000; // wide search window for the two-code HOTP resync (RFC 4226 §7.4)
	public const TOTP_LEEWAY_SECONDS = 10;  // TOTP clock-drift tolerance (otphp requires < period)
}
