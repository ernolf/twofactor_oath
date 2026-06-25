/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, it, expect } from 'vitest'

import {
	ALGORITHM,
	TYPE,
	ocraSuite,
	ocraChallengeLength,
	base32Length,
	isValidBase32Length,
	periodLabel,
	strictAllowedAlgorithms,
	strictMinDigits,
	customSecretIssue,
} from './constants.js'

describe('ocraSuite', () => {
	it('uses the default challenge length (8) when omitted', () => {
		expect(ocraSuite(ALGORITHM.SHA1, 6)).toBe('OCRA-1:HOTP-SHA1-6:QN08')
	})

	it('encodes algorithm, digits and challenge length', () => {
		expect(ocraSuite(ALGORITHM.SHA256, 8, 10)).toBe('OCRA-1:HOTP-SHA256-8:QN10')
	})

	it('zero-pads the challenge length to two digits', () => {
		expect(ocraSuite(ALGORITHM.SHA1, 6, 4)).toBe('OCRA-1:HOTP-SHA1-6:QN04')
	})

	it('falls back to SHA1 for an unknown algorithm code', () => {
		expect(ocraSuite(99, 6)).toBe('OCRA-1:HOTP-SHA1-6:QN08')
	})
})

describe('ocraChallengeLength', () => {
	it('extracts the QN length from a suite', () => {
		expect(ocraChallengeLength('OCRA-1:HOTP-SHA256-8:QN10')).toBe(10)
	})

	it('defaults to 8 for an empty or null suite', () => {
		expect(ocraChallengeLength('')).toBe(8)
		expect(ocraChallengeLength(null)).toBe(8)
	})
})

describe('base32Length', () => {
	it('rounds up to whole Base32 characters', () => {
		expect(base32Length(20)).toBe(32)
		expect(base32Length(16)).toBe(26)
		expect(base32Length(10)).toBe(16)
	})
})

describe('isValidBase32Length', () => {
	it('accepts lengths that decode to whole bytes', () => {
		expect(isValidBase32Length(32)).toBe(true)
		expect(isValidBase32Length(26)).toBe(true)
	})

	it('rejects lengths where (length mod 8) is 1, 3 or 6', () => {
		expect(isValidBase32Length(25)).toBe(false)
		expect(isValidBase32Length(27)).toBe(false)
		expect(isValidBase32Length(30)).toBe(false)
	})
})

describe('periodLabel', () => {
	it('formats seconds, minutes and a mix', () => {
		expect(periodLabel(30)).toBe('30s')
		expect(periodLabel(60)).toBe('1m')
		expect(periodLabel(90)).toBe('1m30s')
		expect(periodLabel(600)).toBe('10m')
	})
})

describe('strictAllowedAlgorithms', () => {
	it('limits HOTP to SHA1 only', () => {
		expect(strictAllowedAlgorithms(TYPE.HOTP)).toEqual([ALGORITHM.SHA1])
	})

	it('allows SHA1/256/512 for TOTP and OCRA', () => {
		const expected = [ALGORITHM.SHA1, ALGORITHM.SHA256, ALGORITHM.SHA512]
		expect(strictAllowedAlgorithms(TYPE.TOTP)).toEqual(expected)
		expect(strictAllowedAlgorithms(TYPE.OCRA)).toEqual(expected)
	})
})

describe('strictMinDigits', () => {
	it('requires 6 digits for HOTP/TOTP and 4 for OCRA', () => {
		expect(strictMinDigits(TYPE.HOTP)).toBe(6)
		expect(strictMinDigits(TYPE.TOTP)).toBe(6)
		expect(strictMinDigits(TYPE.OCRA)).toBe(4)
	})
})

describe('customSecretIssue', () => {
	it('treats empty input as valid (null)', () => {
		expect(customSecretIssue('')).toBeNull()
		expect(customSecretIssue(null)).toBeNull()
	})

	it('flags non-Base32 characters', () => {
		expect(customSecretIssue('abc!@#')).toBe('chars')
	})

	it('flags a length that does not decode to whole bytes', () => {
		expect(customSecretIssue('A'.repeat(27))).toBe('length')
	})

	it('flags a secret below the minimum key length', () => {
		expect(customSecretIssue('A'.repeat(24))).toBe('short')
	})

	it('accepts a normalized 32-character secret (lowercase, spaces, padding)', () => {
		const padded = `${'a'.repeat(32).match(/.{1,4}/g).join(' ')}==`
		expect(customSecretIssue(padded)).toBeNull()
	})
})
