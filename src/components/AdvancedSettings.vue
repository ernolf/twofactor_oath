<!--
  - SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="otp-advanced-form">
		<NcCheckboxRadioSwitch :model-value="strict"
			type="switch"
			@update:model-value="setStrict">
			{{ t('twofactor_oath', 'Strict RFC compliance (grey out options the relevant RFC does not cover)') }}
		</NcCheckboxRadioSwitch>

		<fieldset class="otp-advanced-form__group">
			<legend>{{ t('twofactor_oath', 'Type') }}</legend>
			<div class="otp-advanced-form__radios">
				<NcCheckboxRadioSwitch :model-value="String(type)"
					value="1"
					name="otp-type"
					type="radio"
					@update:model-value="type = Number($event)">
					{{ t('twofactor_oath', 'TOTP (time-based)') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch :model-value="String(type)"
					value="2"
					name="otp-type"
					type="radio"
					@update:model-value="type = Number($event)">
					{{ t('twofactor_oath', 'HOTP (counter-based)') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch :model-value="String(type)"
					value="3"
					name="otp-type"
					type="radio"
					@update:model-value="type = Number($event)">
					{{ t('twofactor_oath', 'OCRA (challenge-response)') }}
				</NcCheckboxRadioSwitch>
			</div>
		</fieldset>

		<fieldset class="otp-advanced-form__group">
			<legend>{{ t('twofactor_oath', 'Algorithm') }}</legend>
			<div class="otp-advanced-form__radios">
				<NcCheckboxRadioSwitch v-for="opt in ALGORITHM_OPTIONS"
					:key="opt.value"
					:model-value="String(algorithm)"
					:value="String(opt.value)"
					:disabled="algorithmDisabled(opt.value)"
					name="otp-algorithm"
					type="radio"
					@update:model-value="algorithm = Number($event)">
					{{ opt.label }}
				</NcCheckboxRadioSwitch>
			</div>
		</fieldset>

		<div class="otp-advanced-form__row">
			<SelectField :model-value="digits"
				:options="digitsOptions"
				:label="t('twofactor_oath', 'Digits')"
				@update:model-value="digits = $event" />
			<SelectField v-if="type === TYPE.TOTP"
				:model-value="period"
				:options="periodOptions"
				:label="t('twofactor_oath', 'Period')"
				@update:model-value="period = $event" />
			<div v-if="type === TYPE.HOTP" class="otp-advanced-form__field">
				<NcTextField type="number"
					:label="t('twofactor_oath', 'Start counter')"
					:model-value="String(counter)"
					:min="0"
					@update:model-value="counter = Number($event)" />
			</div>
			<SelectField v-if="type === TYPE.OCRA"
				:model-value="challengeLength"
				:options="challengeOptions"
				:label="t('twofactor_oath', 'Challenge length')"
				@update:model-value="challengeLength = $event" />
		</div>

		<p v-if="type === TYPE.OCRA" class="otp-advanced-form__suite">
			{{ t('twofactor_oath', 'Resulting OCRA suite:') }} <code>{{ suite }}</code>
		</p>

		<NcTextField :model-value="secret"
			:label="t('twofactor_oath', 'Custom secret (Base32, optional)')"
			:error="secretError !== ''"
			:helper-text="secretError !== '' ? secretError : t('twofactor_oath', 'Leave empty for a random secret. Allowed characters: A–Z and 2–7.')"
			@update:model-value="secret = $event" />
	</div>
</template>

<script>
import { NcCheckboxRadioSwitch, NcTextField } from '@nextcloud/vue'

import { TYPE, DIGITS_MIN, DIGITS_MAX, ALGORITHM_OPTIONS, PERIOD_VALUES, periodLabel, ocraSuite, strictAllowedAlgorithms, strictMinDigits, customSecretIssue } from '../constants.js'
import SelectField from './SelectField.vue'

const ALL_ALGORITHMS = ALGORITHM_OPTIONS.map((o) => o.value)

export default {
	name: 'AdvancedSettings',
	components: {
		NcCheckboxRadioSwitch,
		NcTextField,
		SelectField,
	},
	props: {
		modelValue: {
			type: Object,
			required: true,
		},
	},
	emits: ['update:modelValue'],
	setup() {
		return { TYPE, ALGORITHM_OPTIONS }
	},
	data() {
		return {
			// UI-only guard rail; the backend always accepts the full range.
			strict: true,
		}
	},
	computed: {
		periodOptions() {
			return PERIOD_VALUES.map((seconds) => ({ value: seconds, label: periodLabel(seconds) }))
		},
		digitsOptions() {
			const min = this.strict ? strictMinDigits(this.type) : DIGITS_MIN
			const options = []
			for (let d = min; d <= DIGITS_MAX; d++) {
				options.push({ value: d, label: String(d) })
			}
			return options
		},
		challengeOptions() {
			// RFC 6287 allows a challenge length of 04-64; capped at 10 like the digits.
			const options = []
			for (let n = DIGITS_MIN; n <= DIGITS_MAX; n++) {
				options.push({ value: n, label: String(n) })
			}
			return options
		},
		type: {
			get() {
				return this.modelValue.type
			},
			set(value) {
				this.update('type', value)
				this.$nextTick(() => this.normalize())
			},
		},
		algorithm: {
			get() {
				return this.modelValue.algorithm
			},
			set(value) {
				this.update('algorithm', value)
			},
		},
		digits: {
			get() {
				return this.modelValue.digits
			},
			set(value) {
				this.update('digits', value)
			},
		},
		period: {
			get() {
				return this.modelValue.period
			},
			set(value) {
				this.update('period', value)
			},
		},
		counter: {
			get() {
				return this.modelValue.counter
			},
			set(value) {
				this.update('counter', value)
			},
		},
		secret: {
			get() {
				return this.modelValue.secret
			},
			set(value) {
				this.update('secret', value)
			},
		},
		challengeLength: {
			get() {
				return this.modelValue.challengeLength
			},
			set(value) {
				this.update('challengeLength', value)
			},
		},
		suite() {
			return ocraSuite(this.algorithm, this.digits, this.challengeLength)
		},
		secretError() {
			const issue = customSecretIssue(this.secret)
			if (issue === 'chars') {
				return t('twofactor_oath', 'Only the characters A–Z and 2–7 are allowed.')
			}
			if (issue === 'length') {
				return t('twofactor_oath', 'This length does not decode to whole bytes and would be rejected by authenticator apps.')
			}
			if (issue === 'short') {
				return t('twofactor_oath', 'Secret is too short (minimum 128 bit / 16 bytes).')
			}
			return ''
		},
	},
	methods: {
		update(key, value) {
			this.$emit('update:modelValue', { ...this.modelValue, [key]: value })
		},

		setStrict(value) {
			this.strict = value
			this.$nextTick(() => this.normalize())
		},

		allowedAlgorithms() {
			return this.strict ? strictAllowedAlgorithms(this.type) : ALL_ALGORITHMS
		},

		algorithmDisabled(value) {
			return !this.allowedAlgorithms().includes(value)
		},

		// Keep algorithm + digits inside the currently allowed set (strict + type).
		normalize() {
			const allowed = this.allowedAlgorithms()
			if (!allowed.includes(this.algorithm)) {
				this.algorithm = allowed[0]
			}
			const min = this.strict ? strictMinDigits(this.type) : DIGITS_MIN
			if (this.digits < min) {
				this.digits = min
			}
		},
	},
}
</script>

<style scoped>
.otp-advanced-form {
	display: flex;
	flex-direction: column;
	gap: 12px;
	margin-block: 8px;
	text-align: start;
}

.otp-advanced-form__group {
	border: none;
	margin: 0;
	padding: 0;
}

.otp-advanced-form__group legend {
	font-weight: bold;
	margin-bottom: 4px;
}

.otp-advanced-form__radios {
	display: flex;
	flex-wrap: wrap;
	gap: 4px 24px;
}

.otp-advanced-form__row {
	display: flex;
	gap: 12px;
	flex-wrap: wrap;
	align-items: flex-end;
}

/* Only the HOTP counter (an NcTextField) needs a fixed width; the SelectField
   dropdowns size themselves to their label/options. */
.otp-advanced-form__field {
	width: 8em;
}

.otp-advanced-form__suite {
	color: var(--color-text-maxcontrast);
}

.otp-advanced-form__suite code {
	font-family: monospace;
}
</style>
