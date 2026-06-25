/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { PwdConfirmationMode, addPasswordConfirmationInterceptors } from '@nextcloud/password-confirmation'

// Enable the official password-confirmation flow on our axios instance, so a
// request can force a fresh password with { confirmPassword: PwdConfirmationMode.Strict }.
// The interceptor prompts every time (strict) and sends the password as Basic Auth,
// which the server-side PasswordConfirmationRequired(strict) attribute validates.
addPasswordConfirmationInterceptors(Axios)

export const saveState = async (data) => {
	const url = generateUrl('/apps/twofactor_oath/settings/enable')

	const resp = await Axios.post(url, data)
	return resp.data
}

export const resyncOtp = async (code1, code2) => {
	const url = generateUrl('/apps/twofactor_oath/settings/resync')

	const resp = await Axios.post(url, { code1, code2 })
	return resp.data
}

export const getOtpConfig = async () => {
	const url = generateUrl('/apps/twofactor_oath/settings/config')

	const resp = await Axios.get(url)
	return resp.data.config
}

// Reveal the current secret/QR. The password is forced on every call by the
// strict password-confirmation interceptor (no body password needed).
export const showOtp = async () => {
	const url = generateUrl('/apps/twofactor_oath/settings/show')

	const resp = await Axios.post(url, {}, { confirmPassword: PwdConfirmationMode.Strict })
	return resp.data
}

// Disable OTP. Password forced on every call by the strict interceptor.
export const deactivateOtp = async () => {
	const url = generateUrl('/apps/twofactor_oath/settings/deactivate')

	const resp = await Axios.post(url, {}, { confirmPassword: PwdConfirmationMode.Strict })
	return resp.data
}
