<!--
  - SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="otp-setup" :class="{ 'otp-setup--centered': centered }">
		<div class="otp-setup__note">
			<div class="otp-setup__secret-head">
				<span>{{ t('twofactor_oath', 'Your new secret is:') }}</span>
				<NcButton
					variant="tertiary"
					class="otp-setup__copy"
					:title="t('twofactor_oath', 'Copy secret')"
					:aria-label="t('twofactor_oath', 'Copy secret')"
					@click="copySecret">
					<template #icon>
						<svg
							width="20"
							height="20"
							viewBox="0 0 24 24"
							fill="currentColor">
							<path d="M19,21H8V7H19M19,5H8A2,2 0 0,0 6,7V21A2,2 0 0,0 8,23H19A2,2 0 0,0 21,21V7A2,2 0 0,0 19,5M16,1H4A2,2 0 0,0 2,3V17H4V3H16V1Z" />
						</svg>
					</template>
				</NcButton>
			</div>
			<p class="otp-setup__secret">
				{{ secret }}
			</p>
		</div>

		<div v-if="!ocra" class="otp-setup__qr-section">
			<p>{{ t('twofactor_oath', 'For quick setup, scan this QR code with your authenticator app:') }}</p>
			<div class="otp-setup__qr-row">
				<div class="otp-setup__qr" :class="{ 'otp-setup__qr--stale': stale }">
					<Qrcode
						:value="qrUrl"
						:options="{ width: 200, errorCorrectionLevel: 'H' }"
						class="otp-setup__qr-canvas" />
					<!-- Cosmetic centered logo: the same icon the QR carries in image=, app icon as fallback. -->
					<img
						class="otp-setup__qr-logo"
						:src="iconUrl"
						alt=""
						@error="iconFailed = true">
				</div>
				<div class="otp-setup__qr-hint">
					<p>{{ t('twofactor_oath', 'The icon in the center is used by FreeOTP.') }}</p>
					<p>{{ t('twofactor_oath', 'Other authenticator apps do not display it.') }}</p>
				</div>
			</div>
			<p>{{ t('twofactor_oath', 'Then enter a generated code below to confirm and activate:') }}</p>
		</div>
		<div v-else class="otp-setup__qr-section">
			<p>{{ t('twofactor_oath', 'Enter this challenge into your OCRA token, then type the response below to confirm:') }}</p>
			<p class="otp-setup__challenge" :class="{ 'otp-setup__challenge--stale': stale }">
				{{ challenge }}
			</p>
		</div>
		<div class="otp-setup__confirm">
			<div class="otp-setup__code">
				<NcTextField
					v-model="code"
					:label="ocra ? t('twofactor_oath', 'Response') : t('twofactor_oath', 'Authentication code')"
					:disabled="loading"
					autocomplete="one-time-code"
					inputmode="numeric"
					@keydown.enter="confirm" />
			</div>
			<NcButton
				variant="primary"
				:disabled="loading || code === ''"
				@click="confirm">
				{{ t('twofactor_oath', 'Verify') }}
			</NcButton>
			<NcButton
				v-if="!mandatory"
				variant="tertiary"
				:disabled="loading"
				@click="$emit('cancel')">
				{{ t('twofactor_oath', 'Cancel') }}
			</NcButton>
		</div>
	</div>
</template>

<script>
import Qrcode from '@chenfengyuan/vue-qrcode'
import { imagePath } from '@nextcloud/router'
import { NcButton, NcTextField } from '@nextcloud/vue'

export default {
	name: 'SetupConfirmation',
	components: {
		Qrcode,
		NcButton,
		NcTextField,
	},

	props: {
		secret: {
			type: String,
			required: true,
		},

		qrUrl: {
			type: String,
			required: true,
		},

		loading: {
			type: Boolean,
			default: false,
		},

		stale: {
			type: Boolean,
			default: false,
		},

		mandatory: {
			type: Boolean,
			default: false,
		},

		centered: {
			type: Boolean,
			default: false,
		},

		ocra: {
			type: Boolean,
			default: false,
		},

		challenge: {
			type: String,
			default: '',
		},
	},

	emits: ['confirm', 'cancel'],
	data() {
		return {
			code: '',
			iconFailed: false,
		}
	},

	computed: {
		// Centered QR logo: the icon carried in the otpauth image= parameter, with
		// the (dark, visible on white) app icon as fallback if it is absent or fails.
		iconUrl() {
			if (this.iconFailed) {
				return imagePath('twofactor_oath', 'app-dark.svg')
			}
			try {
				const image = new URL(this.qrUrl).searchParams.get('image')
				if (image !== null && image !== '') {
					return image
				}
			} catch {
				// Fall through to the app icon below.
			}
			return imagePath('twofactor_oath', 'app-dark.svg')
		},
	},

	watch: {
		// Clear the entered code once it no longer matches what is shown: when the
		// settings change (QR/challenge goes stale) or a fresh secret is generated.
		stale(value) {
			if (value) {
				this.code = ''
			}
		},

		qrUrl() {
			this.code = ''
			// Retry the embedded icon for the freshly generated QR.
			this.iconFailed = false
			this.focusCode()
		},

		challenge() {
			this.code = ''
			this.focusCode()
		},
	},

	mounted() {
		this.focusCode()
	},

	methods: {
		// Focus the code/response field after the DOM updates. Called on first mount
		// and whenever a new QR or challenge is generated, so the user can type the
		// code immediately without first clicking into the field.
		focusCode() {
			this.$nextTick(() => {
				const input = this.$el.querySelector('input')
				if (input !== null && !input.disabled) {
					input.focus()
				}
			})
		},

		confirm() {
			if (this.code === '') {
				return
			}
			this.$emit('confirm', this.code)
		},

		async copySecret() {
			try {
				await navigator.clipboard.writeText(this.secret)
				OC.Notification.showTemporary(t('twofactor_oath', 'Secret copied to clipboard'))
			} catch {
				OC.Notification.showTemporary(t('twofactor_oath', 'Could not copy secret'))
			}
		},
	},
}
</script>

<style scoped>
/* Personal settings: bounded width, left-aligned. */
.otp-setup {
	max-width: 40rem;
}

/* Login setup: fill the (already narrow) login card and center the QR block. */
.otp-setup--centered {
	max-width: none;
}

.otp-setup__note {
	display: flex;
	flex-direction: column;
	gap: 4px;
	margin-bottom: 12px;
	padding: 12px;
	text-align: start;
	border-radius: var(--border-radius);
	background-color: var(--color-background-hover);
	border-inline-start: 4px solid var(--color-primary-element);
}

.otp-setup__secret-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 8px;
}

.otp-setup__secret {
	min-width: 0;
	font-family: monospace;
	white-space: nowrap;
	overflow-x: auto;
}

.otp-setup__challenge {
	font-family: monospace;
	font-size: 1.4em;
	letter-spacing: 0.15em;
	margin: 8px 0;
	transition: filter 0.2s ease, opacity 0.2s ease;
}

/* Lightly blur the challenge while settings differ (text blurs harder than a QR). */
.otp-setup__challenge--stale {
	filter: blur(2px);
	opacity: 0.8;
}

.otp-setup__qr-section {
	text-align: start;
}

/* QR on the left, the FreeOTP hint vertically centered to its right. */
.otp-setup__qr-row {
	display: flex;
	align-items: center;
	gap: 16px;
}

.otp-setup__qr-hint {
	max-width: 12em;
	color: var(--color-text-maxcontrast);
	font-size: 0.8em;
}

.otp-setup__qr-hint p {
	margin: 0;
}

.otp-setup__qr-hint p + p {
	margin-top: 0.8em;
}

.otp-setup--centered .otp-setup__qr-section {
	text-align: center;
}

.otp-setup--centered .otp-setup__qr-row {
	justify-content: center;
}

.otp-setup__qr {
	position: relative;
	display: block;
	width: 200px;
	margin: 12px 0;
	transition: filter 0.2s ease, opacity 0.2s ease;
}

.otp-setup__qr-canvas {
	display: block;
	width: 100%;
	height: auto;
}

/* Cosmetic logo over the center of the QR. The high error-correction level (H) on
   the QR keeps it scannable despite the covered modules. */
.otp-setup__qr-logo {
	position: absolute;
	top: 50%;
	inset-inline-start: 50%;
	transform: translate(-50%, -50%);
	width: 33%;
	height: 33%;
	padding: 4px;
	box-sizing: border-box;
	object-fit: contain;
	background: #fff;
	border-radius: 6px;
}

/* Blur the QR (logo included) while the settings differ from what it was generated with. */
.otp-setup__qr--stale {
	filter: blur(5px);
	opacity: 0.5;
}

.otp-setup__confirm {
	display: flex;
	gap: 12px;
	align-items: flex-end;
}

/* Login setup: center the code field + Verify button as a group. */
.otp-setup--centered .otp-setup__confirm {
	justify-content: center;
}

/* Just wide enough for a code of up to 10 digits, so the Verify button fits. */
.otp-setup__code {
	width: 14em;
	flex: 0 0 auto;
}
</style>
