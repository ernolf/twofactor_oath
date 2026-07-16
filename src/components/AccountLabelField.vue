<!--
  - SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="otp-label">
		<div v-if="!editing" class="otp-label__row" :title="managed ? hint : null">
			<span class="otp-label__text">{{ t('twofactor_oath', 'Account label') }}: <span class="otp-label__value">{{ effectiveLocal }}{{ suffix }}</span></span>
			<NcButton
				variant="tertiary"
				:title="t('twofactor_oath', 'Edit label')"
				:aria-label="t('twofactor_oath', 'Edit label')"
				@click="startEdit">
				<template #icon>
					<svg
						width="20"
						height="20"
						viewBox="0 0 24 24"
						fill="currentColor">
						<path d="M20.71,7.04C21.1,6.65 21.1,6 20.71,5.63L18.37,3.29C18,2.9 17.35,2.9 16.96,3.29L15.12,5.12L18.87,8.87M3,17.25V21H6.75L17.81,9.93L14.06,6.18L3,17.25Z" />
					</svg>
				</template>
			</NcButton>
			<NcButton
				v-if="!managed"
				variant="tertiary"
				:title="t('twofactor_oath', 'About the account label')"
				:aria-label="t('twofactor_oath', 'About the account label')"
				@click="showHelp = !showHelp">
				<template #icon>
					<svg
						width="20"
						height="20"
						viewBox="0 0 24 24"
						fill="currentColor">
						<path d="M15.07,11.25L14.17,12.17C13.45,12.89 13,13.5 13,15H11V14.5C11,13.39 11.45,12.39 12.17,11.67L13.41,10.41C13.78,10.05 14,9.55 14,9C14,7.89 13.1,7 12,7A2,2 0 0,0 10,9H8A4,4 0 0,1 12,5A4,4 0 0,1 16,9C16,9.88 15.64,10.67 15.07,11.25M13,19H11V17H13M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2Z" />
					</svg>
				</template>
			</NcButton>
		</div>

		<div v-else class="otp-label__row">
			<NcTextField
				v-model="draft"
				class="otp-label__field"
				:label="t('twofactor_oath', 'Account label')"
				@keydown.enter="confirm"
				@keydown.esc="cancelEdit" />
			<span class="otp-label__suffix">{{ suffix }}</span>
			<NcButton
				variant="tertiary"
				:title="t('twofactor_oath', 'Apply')"
				:aria-label="t('twofactor_oath', 'Apply')"
				@click="confirm">
				<template #icon>
					<svg
						width="20"
						height="20"
						viewBox="0 0 24 24"
						fill="currentColor">
						<path d="M21,7L9,19L3.5,13.5L4.91,12.09L9,16.17L19.59,5.59L21,7Z" />
					</svg>
				</template>
			</NcButton>
			<NcButton
				v-if="showReset"
				variant="tertiary"
				:title="t('twofactor_oath', 'Reset to default')"
				:aria-label="t('twofactor_oath', 'Reset to default')"
				@click="reset">
				<template #icon>
					<svg
						width="20"
						height="20"
						viewBox="0 0 24 24"
						fill="currentColor">
						<path d="M13,3A9,9 0 0,0 4,12H1L4.89,15.89L4.96,16.03L9,12H6A7,7 0 0,1 13,5A7,7 0 0,1 20,12A7,7 0 0,1 13,19C11.07,19 9.32,18.21 8.06,16.94L6.64,18.36C8.27,20 10.5,21 13,21A9,9 0 0,0 22,12A9,9 0 0,0 13,3Z" />
					</svg>
				</template>
			</NcButton>
		</div>

		<NcNoteCard v-if="!managed && showHelp" type="info" class="otp-label__help">
			{{ hint }}
		</NcNoteCard>
	</div>
</template>

<script>
import { NcButton, NcNoteCard, NcTextField } from '@nextcloud/vue'
import { accountLabel, withAccountLabel } from '../otpauth.js'

export default {
	name: 'AccountLabelField',
	components: {
		NcButton,
		NcNoteCard,
		NcTextField,
	},

	props: {
		// Server otpauth:// URI. The default label is derived from it; a custom
		// label overrides it and is kept across URI changes.
		uri: {
			type: String,
			required: true,
		},

		// Admin (managed accounts) view: help as a hover tooltip instead of a
		// click box, with provisioning-appropriate wording.
		managed: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['update'],
	data() {
		return {
			// The user's chosen local part; null means "use the default".
			custom: null,
			editing: false,
			draft: '',
			showHelp: false,
		}
	},

	computed: {
		// The default local part derived from the current server URI.
		defaultLocal() {
			return accountLabel(this.uri).local
		},

		// The fixed `@host` part of the label, shown read-only next to the value.
		suffix() {
			return accountLabel(this.uri).suffix
		},

		// The local part in effect: the custom value, or the default.
		effectiveLocal() {
			return this.custom !== null ? this.custom : this.defaultLocal
		},

		// The reset button appears only while editing a value that differs from the default.
		showReset() {
			return this.editing && this.draft.trim() !== this.defaultLocal
		},

		// Help text; shown in the info box (setup) or as a tooltip (managed view).
		hint() {
			return t('twofactor_oath', 'This label is used only to identify the account in apps that scan the QR code. You can change it, for example shorten it or give it a clearer name. Such a change has no effect on the secret; it only enters the QR code and is not stored on the server. That is why a change you make here is not shown the next time the QR code is displayed.')
		},

		// The URI patched with the custom label, or '' to signal "use the default"
		// (the parent then falls back to the pristine server URI).
		patched() {
			return this.custom !== null && this.custom !== '' ? withAccountLabel(this.uri, this.custom) : ''
		},
	},

	watch: {
		// Emit whenever the effective URI changes; never on mount.
		patched(value) {
			this.$emit('update', value)
		},
	},

	methods: {
		startEdit() {
			this.draft = this.effectiveLocal
			this.editing = true
			this.$nextTick(() => {
				const input = this.$el.querySelector('input')
				if (input !== null) {
					input.focus()
					input.select()
				}
			})
		},

		confirm() {
			// Strip colons (reserved as otpauth separator), so the stored label
			// always matches what ends up in the QR code.
			const value = this.draft.replaceAll(':', '').trim()
			// Empty or equal to the default means "no custom label".
			this.custom = value === '' || value === this.defaultLocal ? null : value
			this.editing = false
		},

		reset() {
			this.custom = null
			this.editing = false
		},

		cancelEdit() {
			// Discard the draft; the confirmed custom label stays as it was.
			this.editing = false
		},
	},
}
</script>

<style scoped>
.otp-label {
	margin: 12px 0;
	text-align: start;
	align-self: stretch;
}

.otp-label__row {
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	gap: 4px 8px;
}

.otp-label__text {
	overflow-wrap: anywhere;
}

.otp-label__field {
	flex: 1 1 12em;
	min-width: 0;
}

.otp-label__value,
.otp-label__suffix {
	color: var(--color-text-maxcontrast);
}

.otp-label__help {
	margin-top: 8px;
	text-align: start;
}
</style>
