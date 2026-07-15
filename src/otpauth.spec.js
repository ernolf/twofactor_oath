/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'
import { accountLabel, withAccountLabel } from './otpauth.js'

const uri = 'otpauth://totp/My%20Cloud%3Aalice%40cloud.example?secret=GEZDGNBV&issuer=My%20Cloud&image=https%3A%2F%2Fcloud.example%2Ffavicon'

describe('accountLabel', () => {
	it('splits the account into local part and host suffix', () => {
		expect(accountLabel(uri)).toEqual({ local: 'alice', suffix: '@cloud.example' })
	})

	it('keeps an @ inside the local part (legacy guest UIDs)', () => {
		const guest = 'otpauth://totp/Cloud%3Auser%40mail.example%40cloud.example?secret=A'
		expect(accountLabel(guest)).toEqual({ local: 'user@mail.example', suffix: '@cloud.example' })
	})

	it('returns an empty suffix when the label has no host', () => {
		expect(accountLabel('otpauth://totp/Cloud%3Aalice?secret=A')).toEqual({ local: 'alice', suffix: '' })
	})
})

describe('withAccountLabel', () => {
	it('replaces only the local part and keeps issuer, host and query', () => {
		expect(withAccountLabel(uri, 'jan')).toBe('otpauth://totp/My%20Cloud%3Ajan%40cloud.example?secret=GEZDGNBV&issuer=My%20Cloud&image=https%3A%2F%2Fcloud.example%2Ffavicon')
	})

	it('keeps the URI unchanged for a blank input', () => {
		expect(withAccountLabel(uri, '')).toBe(uri)
		expect(withAccountLabel(uri, '   ')).toBe(uri)
	})

	it('strips colons, which otpauth reserves as separator', () => {
		expect(withAccountLabel(uri, 'a:b')).toContain('My%20Cloud%3Aab%40cloud.example')
	})
})
