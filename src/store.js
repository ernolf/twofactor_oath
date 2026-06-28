/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { STATE } from './constants.js'
import { saveState } from './services/StateService.js'

export const useOtpStore = defineStore('otp', {
	state: () => ({
		otpState: undefined,
	}),
	actions: {
		async enable(settings = {}) {
			const { state, secret, qrUrl, challenge } = await saveState({ state: STATE.CREATED, ...settings })
			this.otpState = state
			return { secret, qrUrl, challenge }
		},

		async confirm(code) {
			const { state } = await saveState({ state: STATE.ENABLED, code })
			this.otpState = state
		},

		async disable() {
			const { state } = await saveState({ state: STATE.DISABLED })
			this.otpState = state
		},
	},
})
