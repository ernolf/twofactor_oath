/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

// Mirrors lib/Constants.php so the UI uses the same codes as the backend.

export const STATE = Object.freeze({
	DISABLED: 0,
	CREATED: 1,
	ENABLED: 2,
})

export const TYPE = Object.freeze({
	TOTP: 1,
	HOTP: 2,
	OCRA: 3,
})

export const ALGORITHM = Object.freeze({
	SHA1: 1,
	SHA224: 2,
	SHA256: 3,
	SHA384: 4,
	SHA512: 5,
})

// Algorithm choices in display order (value = integer code, for personal/login UI).
export const ALGORITHM_OPTIONS = [
	{ value: 1, label: 'SHA-1' },
	{ value: 2, label: 'SHA-224' },
	{ value: 3, label: 'SHA-256' },
	{ value: 4, label: 'SHA-384' },
	{ value: 5, label: 'SHA-512' },
]

// Same in display order, keyed by the CSV/name representation (for the admin table).
export const ALGORITHM_NAME_OPTIONS = [
	{ value: 'sha1', label: 'SHA-1' },
	{ value: 'sha224', label: 'SHA-224' },
	{ value: 'sha256', label: 'SHA-256' },
	{ value: 'sha384', label: 'SHA-384' },
	{ value: 'sha512', label: 'SHA-512' },
]

export const DEFAULTS = Object.freeze({
	type: TYPE.TOTP,
	algorithm: ALGORITHM.SHA1,
	digits: 6,
	period: 30,
	counter: 0,
	epoch: 0,
	secret: '',
	challengeLength: 8,
})

// Hash name (as used inside an OCRA suite string) per algorithm code.
const ALGORITHM_SUITE_HASH = Object.freeze({
	1: 'SHA1',
	2: 'SHA224',
	3: 'SHA256',
	4: 'SHA384',
	5: 'SHA512',
})

// OCRA challenge: numeric (QN), length is configurable (RFC 6287 allows 04-64).
export const OCRA_CHALLENGE_DEFAULT = 8

/**
 * Compose an OCRA suite (RFC 6287) from algorithm code, digit count and challenge length.
 * @param {number} algorithm algorithm code (see ALGORITHM)
 * @param {number} digits response length
 * @param {number} [challengeLength] numeric challenge length (RFC 6287: 4 to 64)
 * @return {string} the OCRA suite string
 */
export function ocraSuite(algorithm, digits, challengeLength = OCRA_CHALLENGE_DEFAULT) {
	const qn = `QN${String(challengeLength).padStart(2, '0')}`
	return `OCRA-1:HOTP-${ALGORITHM_SUITE_HASH[algorithm] || 'SHA1'}-${digits}:${qn}`
}

/**
 * Extract the numeric challenge length from an OCRA suite (default 8).
 * @param {string} suite the OCRA suite string
 * @return {number} the numeric challenge length
 */
export function ocraChallengeLength(suite) {
	const m = /:QN(\d+)/.exec(suite || '')
	return m ? Number(m[1]) : OCRA_CHALLENGE_DEFAULT
}

export const DIGITS_MIN = 4
export const DIGITS_MAX = 10

// "Strict RFC" UI guard rails (UI-only — the backend always accepts the full range).
// HOTP (RFC 4226): SHA-1 only, >= 6 digits. TOTP (RFC 6238): SHA-1/256/512, >= 6
// digits. OCRA (RFC 6287): SHA-1/256/512, >= 4 digits.
/**
 * Algorithms permitted under the "Strict RFC" UI guard for a token type.
 * @param {number} type token type (see TYPE)
 * @return {number[]} the allowed algorithm codes
 */
export function strictAllowedAlgorithms(type) {
	if (type === TYPE.HOTP) {
		return [ALGORITHM.SHA1]
	}
	return [ALGORITHM.SHA1, ALGORITHM.SHA256, ALGORITHM.SHA512]
}

/**
 * Minimum digit count permitted under the "Strict RFC" UI guard for a token type.
 * @param {number} type token type (see TYPE)
 * @return {number} the minimum number of digits
 */
export function strictMinDigits(type) {
	return type === TYPE.OCRA ? 4 : 6
}

// Allowed TOTP periods in seconds (client-compatible steps).
export const PERIOD_VALUES = [15, 20, 25, 30, 45, 60, 90, 120, 180, 240, 300, 600]

/**
 * Compact label for a TOTP period in seconds (e.g. 90 -> "1m30s").
 * @param {number} seconds the period in seconds
 * @return {string} the compact label
 */
export function periodLabel(seconds) {
	const minutes = Math.floor(seconds / 60)
	const rest = seconds % 60
	if (minutes === 0) {
		return `${rest}s`
	}
	if (rest === 0) {
		return `${minutes}m`
	}
	return `${minutes}m${rest}s`
}

// Secret length is configured in bytes of key material. RFC 4226 R6: at least
// 128 bit (16 bytes), recommended 160 bit (20 bytes). Stored Base32 = 5 bits/char.
export const SECRET_BYTES_MIN = 16

/**
 * Base32 (unpadded) character count for a given number of bytes.
 * @param {number} bytes the number of bytes
 * @return {number} the Base32 character count
 */
export function base32Length(bytes) {
	return Math.ceil((bytes * 8) / 5)
}

// Preset strengths offered for the admin default length (bytes = key material).
export const SECRET_PRESETS = [
	{ key: 'minimal', bytes: 16 },
	{ key: 'recommended', bytes: 20 },
	{ key: 'high', bytes: 40 },
	{ key: 'extreme', bytes: 60 },
	{ key: 'paranoia', bytes: 80 },
]

export const BASE32_RE = /^[A-Z2-7]+$/

/**
 * A Base32 length decodes to whole bytes unless (length mod 8) is 1, 3 or 6.
 * @param {number} length the Base32 character count
 * @return {boolean} true if the length decodes to whole bytes
 */
export function isValidBase32Length(length) {
	return ![1, 3, 6].includes(length % 8)
}

/**
 * Validate a custom Base32 secret as the user types.
 * @param {string} secret the raw input
 * @return {?string} null if empty/valid, else an issue code: 'chars' | 'length' | 'short'
 */
export function customSecretIssue(secret) {
	if (!secret) {
		return null
	}
	const s = secret.toUpperCase().replace(/\s+/g, '').replace(/=+$/, '')
	if (!BASE32_RE.test(s)) {
		return 'chars'
	}
	if (!isValidBase32Length(s.length)) {
		return 'length'
	}
	if (Math.floor((s.length * 5) / 8) < SECRET_BYTES_MIN) {
		return 'short'
	}
	return null
}
