<!--
  - SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div>
		<NcNoteCard v-if="managed" type="info">
			{{ t('twofactor_oath', 'Your OTP token is managed by your administrator and cannot be changed here.') }}
		</NcNoteCard>

		<NcCheckboxRadioSwitch v-if="!managed"
			:model-value="enabled"
			type="switch"
			:disabled="loading"
			@update:model-value="toggle">
			{{ t('twofactor_oath', 'Enable OTP two-factor authentication') }}
		</NcCheckboxRadioSwitch>

		<div v-if="!managed && secret" class="otp-setup">
			<SetupConfirmation :secret="secret"
				:qr-url="qrUrl"
				:ocra="settings.type === TYPE.OCRA"
				:challenge="ocraChallenge"
				:loading="loadingConfirmation"
				:stale="dirty"
				@confirm="confirm"
				@cancel="cancelSetup" />

			<div class="otp-setup__advanced-bar">
				<NcCheckboxRadioSwitch :model-value="showAdvanced"
					type="switch"
					@update:model-value="showAdvanced = $event">
					{{ t('twofactor_oath', 'Advanced settings') }}
				</NcCheckboxRadioSwitch>
				<NcButton v-if="showAdvanced && dirty"
					variant="primary"
					:disabled="loading || secretInvalid"
					@click="recreate">
					{{ settings.type === TYPE.OCRA ? t('twofactor_oath', 'Recreate challenge with these settings') : t('twofactor_oath', 'Recreate QR code with these settings') }}
				</NcButton>
			</div>

			<div v-if="showAdvanced" class="otp-setup__advanced">
				<AdvancedSettings v-model="settings" />
			</div>
		</div>

		<div v-if="!managed && enabled && !secret" class="otp-current">
			<NcButton v-if="!shown" :disabled="showLoading" @click="showConfiguration">
				{{ t('twofactor_oath', 'Show configuration') }}
			</NcButton>

			<div v-if="shown && config" class="otp-current__panel">
				<div class="otp-current__line">
					<span>{{ config.type.toUpperCase() }}</span>
					<template v-if="config.type === 'ocra'">
						<span>{{ config.suite }}</span>
					</template>
					<template v-else>
						<span>{{ algoLabel(config.algorithm) }}</span>
						<span>{{ n('twofactor_oath', '%n digit', '%n digits', config.digits) }}</span>
						<span v-if="config.type === 'totp'">{{ periodLabel(config.period) }}</span>
						<span v-else>{{ t('twofactor_oath', 'Counter: {n}', { n: config.counter }) }}</span>
					</template>
				</div>

				<NcButton v-if="!secretShown" @click="reveal">
					{{ config.type === 'ocra' ? t('twofactor_oath', 'Show secret') : t('twofactor_oath', 'Show secret and QR code') }}
				</NcButton>

				<div v-if="secretShown" class="otp-current__reveal" @click="resetCountdown">
					<div class="otp-current__secret-line">
						<code class="otp-current__secret">{{ secretData.secret }}</code>
						<NcButton variant="tertiary"
							:title="t('twofactor_oath', 'Copy secret')"
							:aria-label="t('twofactor_oath', 'Copy secret')"
							@click="copyCurrentSecret">
							<template #icon>
								<svg width="20"
									height="20"
									viewBox="0 0 24 24"
									fill="currentColor">
									<path d="M19,21H8V7H19M19,5H8A2,2 0 0,0 6,7V21A2,2 0 0,0 8,23H19A2,2 0 0,0 21,21V7A2,2 0 0,0 19,5M16,1H4A2,2 0 0,0 2,3V17H4V3H16V1Z" />
								</svg>
							</template>
						</NcButton>
					</div>
					<div v-if="secretData.uri" class="otp-current__qr-row">
						<div class="otp-current__qr">
							<Qrcode :value="secretData.uri" :options="{ width: 120, errorCorrectionLevel: 'H' }" class="otp-current__qr-canvas" />
							<!-- Cosmetic centered logo: the same icon the QR carries in image=, app icon as fallback. -->
							<img class="otp-current__qr-logo"
								:src="currentIconUrl"
								alt=""
								@error="currentIconFailed = true">
						</div>
						<div class="otp-current__qr-hint">
							<p>{{ t('twofactor_oath', 'The icon in the center is used by the FreeOTP app.') }}</p>
							<p>{{ t('twofactor_oath', 'Other authenticator apps do not display it.') }}</p>
						</div>
					</div>
					<p class="otp-current__countdown">
						{{ t('twofactor_oath', 'Hides in {n}s — click to keep visible', { n: countdown }) }}
					</p>
				</div>

				<details v-if="config.type === 'hotp'" class="otp-resync">
					<summary class="otp-resync__summary">
						{{ t('twofactor_oath', 'HOTP token out of sync?') }}
					</summary>
					<p class="otp-resync__hint">
						{{ t('twofactor_oath', 'If your counter-based (HOTP) token no longer works, enter two consecutive codes from it to re-synchronize the counter.') }}
					</p>
					<div class="otp-resync__fields">
						<NcTextField v-model="resyncCode1"
							:label="t('twofactor_oath', 'Current code')"
							:disabled="resyncLoading"
							inputmode="numeric"
							autocomplete="off" />
						<NcTextField v-model="resyncCode2"
							:label="t('twofactor_oath', 'Next code')"
							:disabled="resyncLoading"
							inputmode="numeric"
							autocomplete="off" />
						<NcButton variant="primary"
							:disabled="resyncLoading || resyncCode1.trim() === '' || resyncCode2.trim() === ''"
							@click="resync">
							{{ t('twofactor_oath', 'Re-synchronize') }}
						</NcButton>
					</div>
				</details>

				<NcButton @click="hideCurrent">
					{{ t('twofactor_oath', 'Hide') }}
				</NcButton>
			</div>
		</div>
	</div>
</template>

<script>
import Qrcode from '@chenfengyuan/vue-qrcode'
import { NcButton, NcCheckboxRadioSwitch, NcNoteCard, NcTextField } from '@nextcloud/vue'
import { confirmPassword } from '@nextcloud/password-confirmation'
import { loadState } from '@nextcloud/initial-state'
import { imagePath } from '@nextcloud/router'

import logger from '../logger.js'
import { STATE, TYPE, DEFAULTS, ALGORITHM_NAME_OPTIONS, periodLabel, ocraSuite, customSecretIssue } from '../constants.js'
import { getOtpConfig, resyncOtp, showOtp, deactivateOtp } from '../services/StateService.js'
import { useOtpStore } from '../store.js'
import AdvancedSettings from './AdvancedSettings.vue'
import SetupConfirmation from './SetupConfirmation.vue'

// Auto-hide the revealed secret/QR after this many seconds (online-banking style).
const AUTO_HIDE_SECONDS = 60

export default {
	name: 'PersonalOtpSettings',
	components: {
		Qrcode,
		NcButton,
		NcCheckboxRadioSwitch,
		NcNoteCard,
		NcTextField,
		AdvancedSettings,
		SetupConfirmation,
	},
	setup() {
		return { otpStore: useOtpStore(), periodLabel, TYPE }
	},
	data() {
		return {
			managed: loadState('twofactor_oath', 'managed', false),
			loading: false,
			loadingConfirmation: false,
			enabled: this.otpStore.otpState === STATE.ENABLED,
			secret: undefined,
			qrUrl: '',
			ocraChallenge: '',
			showAdvanced: false,
			resyncCode1: '',
			resyncCode2: '',
			resyncLoading: false,
			shown: false,
			showLoading: false,
			config: null,
			secretShown: false,
			secretData: null,
			currentIconFailed: false,
			countdown: 0,
			countdownTimer: null,
			settings: { ...DEFAULTS },
			// Snapshot of the settings that produced the current QR; used to
			// detect unsaved changes (dirty) and blur the now-stale QR.
			appliedSettings: null,
		}
	},
	computed: {
		// Centered logo for the revealed QR: the icon carried in the otpauth image=
		// parameter, with the (dark, visible on white) app icon as fallback.
		currentIconUrl() {
			if (!this.currentIconFailed && this.secretData && this.secretData.uri) {
				try {
					const image = new URL(this.secretData.uri).searchParams.get('image')
					if (image !== null && image !== '') {
						return image
					}
				} catch (e) {
					// Fall through to the app icon below.
				}
			}
			return imagePath('twofactor_oath', 'app-dark.svg')
		},
		secretInvalid() {
			return customSecretIssue(this.settings.secret) !== null
		},
		dirty() {
			return this.appliedSettings !== null
				&& JSON.stringify(this.settings) !== JSON.stringify(this.appliedSettings)
		},
	},
	watch: {
		'settings.type'() {
			// During an active setup, regenerate the preview when the type changes
			// so the shown QR (TOTP/HOTP) or challenge (OCRA) matches the settings.
			if (this.secret !== undefined) {
				this.recreate()
			}
		},
	},
	beforeUnmount() {
		this.stopCountdown()
	},
	methods: {
		toggle(checked) {
			if (checked) {
				this.enabled = true
				this.create()
			} else if (this.secret) {
				// Setup started but not confirmed yet -> discard it locally.
				this.cancelSetup()
			} else {
				// Require a fresh password before disabling; the switch stays on
				// (bound to `enabled`) until the password dialog confirms.
				this.disable()
			}
		},

		buildSettings() {
			const s = this.settings
			const payload = {
				type: s.type,
				algorithm: s.algorithm,
				digits: s.digits,
				period: s.period,
				counter: s.counter,
				epoch: s.epoch,
				suite: s.type === TYPE.OCRA ? ocraSuite(s.algorithm, s.digits, s.challengeLength) : null,
			}
			if (s.secret && s.secret.trim() !== '') {
				payload.secret = s.secret.trim()
			}
			return payload
		},

		async create() {
			this.loading = true
			this.hideCurrent()
			try {
				await confirmPassword()
				const { secret, qrUrl, challenge } = await this.otpStore.enable(this.buildSettings())
				this.secret = secret
				this.qrUrl = qrUrl || ''
				this.ocraChallenge = challenge || ''
				this.appliedSettings = { ...this.settings }
			} catch (e) {
				OC.Notification.showTemporary(t('twofactor_oath', 'Could not start OTP setup'))
				logger.error('could not start OTP setup', { e })
				this.enabled = false
			} finally {
				this.loading = false
			}
		},

		// Re-generate the secret/QR with the currently chosen advanced settings.
		recreate() {
			this.create()
		},

		cancelSetup() {
			// Discard a not-yet-confirmed secret; the stale CREATED row is
			// harmless (not enabled) and gets replaced on the next setup.
			this.secret = undefined
			this.qrUrl = ''
			this.ocraChallenge = ''
			this.showAdvanced = false
			this.appliedSettings = null
			this.enabled = false
			this.settings = { ...DEFAULTS }
		},

		async confirm(code) {
			this.loading = true
			this.loadingConfirmation = true
			try {
				await confirmPassword()
				await this.otpStore.confirm(code)
				if (this.otpStore.otpState === STATE.ENABLED) {
					this.enabled = true
					this.secret = undefined
					this.qrUrl = ''
					this.showAdvanced = false
					this.appliedSettings = null
				} else {
					OC.Notification.showTemporary(t('twofactor_oath', 'Could not verify the code. Please try again'))
				}
			} catch (e) {
				OC.Notification.showTemporary(t('twofactor_oath', 'Could not enable OTP'))
				logger.error('could not enable OTP', { e })
			} finally {
				this.loading = false
				this.loadingConfirmation = false
			}
		},

		// The strict password-confirmation interceptor prompts for the password and
		// rejects with this error when the user cancels the dialog.
		isCancelled(e) {
			return e instanceof Error && e.message === 'Dialog closed'
		},

		// Reveal the current secret/QR. The interceptor forces a fresh password.
		async reveal() {
			try {
				const data = await showOtp()
				this.secretData = { secret: data.secret, uri: data.uri }
				this.currentIconFailed = false
				this.secretShown = true
				this.startCountdown()
			} catch (e) {
				if (this.isCancelled(e)) {
					return
				}
				const message = e?.response?.status === 403
					? t('twofactor_oath', 'Wrong password.')
					: t('twofactor_oath', 'Could not reveal the secret')
				OC.Notification.showTemporary(message)
				logger.error('could not reveal secret', { e })
			}
		},

		// Disable OTP. The interceptor forces a fresh password.
		async disable() {
			try {
				await deactivateOtp()
				this.otpStore.otpState = STATE.DISABLED
				this.enabled = false
				this.secret = undefined
				this.qrUrl = ''
				this.ocraChallenge = ''
				this.showAdvanced = false
				this.appliedSettings = null
				this.settings = { ...DEFAULTS }
				this.hideCurrent()
			} catch (e) {
				// Cancelled or failed -> keep 2FA enabled (the switch stays on).
				this.enabled = true
				if (this.isCancelled(e)) {
					return
				}
				const message = e?.response?.status === 403
					? t('twofactor_oath', 'Wrong password.')
					: t('twofactor_oath', 'Could not disable OTP')
				OC.Notification.showTemporary(message)
				logger.error('could not disable OTP', { e })
			}
		},

		// Reveal the non-sensitive configuration (no password needed).
		async showConfiguration() {
			this.showLoading = true
			try {
				this.config = await getOtpConfig()
				this.shown = true
			} catch (e) {
				OC.Notification.showTemporary(t('twofactor_oath', 'Could not load the configuration'))
				logger.error('could not load OTP configuration', { e })
			} finally {
				this.showLoading = false
			}
		},

		startCountdown() {
			this.stopCountdown()
			this.countdown = AUTO_HIDE_SECONDS
			this.countdownTimer = setInterval(() => {
				this.countdown -= 1
				if (this.countdown <= 0) {
					this.hideSecret()
				}
			}, 1000)
		},

		// Clicking the revealed box restarts the timer (keep visible while working).
		resetCountdown() {
			if (this.secretShown) {
				this.countdown = AUTO_HIDE_SECONDS
			}
		},

		stopCountdown() {
			if (this.countdownTimer !== null) {
				clearInterval(this.countdownTimer)
				this.countdownTimer = null
			}
		},

		hideSecret() {
			this.stopCountdown()
			this.secretShown = false
			this.secretData = null
		},

		hideCurrent() {
			this.hideSecret()
			this.shown = false
			this.config = null
		},

		async copyCurrentSecret() {
			try {
				await navigator.clipboard.writeText(this.secretData.secret)
				OC.Notification.showTemporary(t('twofactor_oath', 'Secret copied to clipboard'))
			} catch (e) {
				OC.Notification.showTemporary(t('twofactor_oath', 'Could not copy the secret'))
			}
		},

		algoLabel(value) {
			const opt = ALGORITHM_NAME_OPTIONS.find((o) => o.value === value)
			return opt ? opt.label : value
		},

		async resync() {
			if (this.resyncCode1.trim() === '' || this.resyncCode2.trim() === '') {
				return
			}
			this.resyncLoading = true
			try {
				const { success } = await resyncOtp(this.resyncCode1.trim(), this.resyncCode2.trim())
				if (success) {
					OC.Notification.showTemporary(t('twofactor_oath', 'Token re-synchronized.'))
					this.resyncCode1 = ''
					this.resyncCode2 = ''
				} else {
					OC.Notification.showTemporary(t('twofactor_oath', 'Could not re-synchronize. Check the two consecutive codes and try again.'))
				}
			} catch (e) {
				OC.Notification.showTemporary(t('twofactor_oath', 'Could not re-synchronize the token'))
				logger.error('could not resync OTP', { e })
			} finally {
				this.resyncLoading = false
			}
		},
	},
}
</script>

<style scoped>
.otp-setup__advanced-bar {
	display: flex;
	align-items: center;
	gap: 16px;
	margin-top: 12px;
}

.otp-setup__advanced {
	display: flex;
	flex-direction: column;
	gap: 12px;
	align-items: flex-start;
	margin-top: 12px;
}

.otp-current {
	margin-top: 24px;
}

.otp-current__panel {
	display: flex;
	flex-direction: column;
	gap: 8px;
	align-items: flex-start;
}

.otp-current__line {
	display: flex;
	gap: 12px;
	flex-wrap: wrap;
	color: var(--color-text-maxcontrast);
}

.otp-current__secret-line {
	display: flex;
	align-items: center;
	gap: 4px;
	max-width: 100%;
}

.otp-current__secret {
	min-width: 0;
	max-width: 220px;
	font-family: monospace;
	font-size: 0.85em;
	white-space: nowrap;
	overflow-x: auto;
}

.otp-current__reveal {
	display: flex;
	flex-direction: column;
	gap: 8px;
	align-items: flex-start;
	padding: 12px;
	border-radius: var(--border-radius-large, var(--border-radius));
	background-color: var(--color-background-hover);
	cursor: pointer;
}

.otp-current__qr {
	position: relative;
	width: 120px;
}

.otp-current__qr-canvas {
	display: block;
	width: 100%;
	height: auto;
}

/* Cosmetic logo over the center of the QR; the high error-correction level (H)
   on the QR keeps it scannable despite the covered modules. */
.otp-current__qr-logo {
	position: absolute;
	top: 50%;
	inset-inline-start: 50%;
	transform: translate(-50%, -50%);
	width: 33%;
	height: 33%;
	padding: 3px;
	box-sizing: border-box;
	object-fit: contain;
	background: #fff;
	border-radius: 5px;
}

/* QR on the left, the FreeOTP hint vertically centered to its right. */
.otp-current__qr-row {
	display: flex;
	align-items: center;
	gap: 16px;
}

.otp-current__qr-hint {
	max-width: 12em;
	color: var(--color-text-maxcontrast);
	font-size: 0.8em;
}

.otp-current__qr-hint p {
	margin: 0;
}

.otp-current__qr-hint p + p {
	margin-top: 0.8em;
}

.otp-current__countdown {
	margin: 0;
	color: var(--color-text-maxcontrast);
	font-size: 0.9em;
}

.otp-resync {
	margin-top: 4px;
}

.otp-resync__summary {
	cursor: pointer;
}

.otp-resync__hint {
	margin-top: 8px;
	max-width: 40rem;
	margin-bottom: 8px;
	color: var(--color-text-maxcontrast);
}

.otp-resync__fields {
	display: flex;
	gap: 12px;
	align-items: flex-end;
	flex-wrap: wrap;
}

.otp-resync__fields :deep(.input-field) {
	width: 12em;
}
</style>
