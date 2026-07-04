/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Axios from '@nextcloud/axios'
import { addPasswordConfirmationInterceptors, PwdConfirmationMode } from '@nextcloud/password-confirmation'
import { generateUrl } from '@nextcloud/router'

// Enable the official password-confirmation flow on our axios instance, so a
// request can force a fresh password with { confirmPassword: PwdConfirmationMode.Strict }.
// The interceptor prompts every time (strict) and sends the password as Basic Auth,
// which the server-side PasswordConfirmationRequired(strict) attribute validates.
addPasswordConfirmationInterceptors(Axios)

/**
 * Persist the OTP enrollment state on the server.
 *
 * @param {object} data the OTP settings to enable and store
 */
export async function saveState(data) {
	const url = generateUrl('/apps/twofactor_oath/settings/enable')

	const resp = await Axios.post(url, data)
	return resp.data
}

/**
 * Resynchronise a drifted HOTP counter from two consecutive codes.
 *
 * @param {string} code1 the first generated code
 * @param {string} code2 the next consecutive code
 */
export async function resyncOtp(code1, code2) {
	const url = generateUrl('/apps/twofactor_oath/settings/resync')

	const resp = await Axios.post(url, { code1, code2 })
	return resp.data
}

/**
 * Fetch the user's non-sensitive OTP configuration (null if no enabled token).
 */
export async function getOtpConfig() {
	const url = generateUrl('/apps/twofactor_oath/settings/config')

	const resp = await Axios.get(url)
	return resp.data.config
}

/**
 * Reveal the current secret/QR. The strict password-confirmation interceptor
 * forces a fresh password on every call (no body password needed).
 */
export async function showOtp() {
	const url = generateUrl('/apps/twofactor_oath/settings/show')

	const resp = await Axios.post(url, {}, { confirmPassword: PwdConfirmationMode.Strict })
	return resp.data
}

/**
 * Disable OTP. The strict interceptor forces a fresh password on every call.
 */
export async function deactivateOtp() {
	const url = generateUrl('/apps/twofactor_oath/settings/deactivate')

	const resp = await Axios.post(url, {}, { confirmPassword: PwdConfirmationMode.Strict })
	return resp.data
}
