<!--
  - SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div>
		<NcNoteCard v-if="managed" type="info">
			{{ t('twofactor_oath', 'Your OTP token is managed by your administrator. Please contact them to set it up.') }}
		</NcNoteCard>
		<template v-else>
			<div v-if="loading" class="icon-loading otp-login-setup__loading" />
			<template v-else>
				<SetupConfirmation :secret="secret"
					:qr-url="qrUrl"
					:ocra="settings.type === TYPE.OCRA"
					:challenge="ocraChallenge"
					:loading="confirmationLoading"
					:stale="dirty"
					:cancelable="false"
					:centered="true"
					@confirm="confirm" />

				<div class="otp-login-setup__advanced-bar">
					<NcCheckboxRadioSwitch :model-value="showAdvanced"
						type="switch"
						@update:model-value="showAdvanced = $event">
						{{ t('twofactor_oath', 'Advanced settings') }}
					</NcCheckboxRadioSwitch>
					<NcButton v-if="showAdvanced && dirty"
						variant="primary"
						:disabled="confirmationLoading || secretInvalid"
						@click="recreate">
						{{ settings.type === TYPE.OCRA ? t('twofactor_oath', 'Recreate challenge with these settings') : t('twofactor_oath', 'Recreate QR code with these settings') }}
					</NcButton>
				</div>

				<div v-if="showAdvanced" class="otp-login-setup__advanced">
					<AdvancedSettings v-model="settings" />
				</div>
			</template>

			<!-- Submitting an empty POST to the current login URL lets Nextcloud
			     re-evaluate and continue the login once the token is enabled. -->
			<form ref="confirmForm" method="POST" />
		</template>
	</div>
</template>

<script>
import { NcButton, NcCheckboxRadioSwitch, NcNoteCard } from '@nextcloud/vue'
import { loadState } from '@nextcloud/initial-state'

import logger from '../logger.js'
import { saveState } from '../services/StateService.js'
import { STATE, TYPE, DEFAULTS, ocraSuite, customSecretIssue } from '../constants.js'
import AdvancedSettings from './AdvancedSettings.vue'
import SetupConfirmation from './SetupConfirmation.vue'

export default {
	name: 'LoginSetup',
	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		NcNoteCard,
		AdvancedSettings,
		SetupConfirmation,
	},
	setup() {
		return { TYPE }
	},
	data() {
		return {
			managed: loadState('twofactor_oath', 'managed', false),
			loading: true,
			confirmationLoading: false,
			secret: '',
			qrUrl: '',
			ocraChallenge: '',
			showAdvanced: false,
			settings: { ...DEFAULTS },
			appliedSettings: null,
		}
	},
	computed: {
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
			// Regenerate the preview (QR or OCRA challenge) when the type changes.
			if (this.secret) {
				this.recreate()
			}
		},
	},
	mounted() {
		if (!this.managed) {
			this.generate()
		}
	},
	methods: {
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

		async generate() {
			try {
				const { secret, qrUrl, challenge } = await saveState({ state: STATE.CREATED, ...this.buildSettings() })
				this.secret = secret
				this.qrUrl = qrUrl || ''
				this.ocraChallenge = challenge || ''
				this.appliedSettings = { ...this.settings }
			} catch (e) {
				OC.Notification.showTemporary(t('twofactor_oath', 'Could not start OTP setup'))
				logger.error('could not start OTP login setup', { e })
			} finally {
				this.loading = false
			}
		},

		recreate() {
			this.generate()
		},

		async confirm(code) {
			this.confirmationLoading = true
			try {
				const { state } = await saveState({ state: STATE.ENABLED, code })
				if (state === STATE.ENABLED) {
					this.$refs.confirmForm.submit()
				} else {
					OC.Notification.showTemporary(t('twofactor_oath', 'Could not verify the code. Please try again'))
				}
			} catch (e) {
				OC.Notification.showTemporary(t('twofactor_oath', 'Could not enable OTP'))
				logger.error('could not confirm OTP login setup', { e })
			} finally {
				this.confirmationLoading = false
			}
		},
	},
}
</script>

<style scoped>
.otp-login-setup__loading {
	min-height: 60px;
}

.otp-login-setup__advanced-bar {
	display: flex;
	align-items: center;
	gap: 16px;
	margin-top: 12px;
}

.otp-login-setup__advanced {
	margin-top: 12px;
}
</style>
