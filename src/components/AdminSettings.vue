<!--
  - SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcSettingsSection
		class="otp-admin-section"
		:name="t('twofactor_oath', 'OATH (TOTP/HOTP/OCRA)')"
		:description="t('twofactor_oath', 'Settings for the advanced OATH second-factor provider.')">
		<NcNoteCard v-if="totpEnabled && totpImportCount > 0" type="info" class="otp-admin__banner">
			<p>{{ n('twofactor_oath', '%n account configured in the bundled twofactor_totp app can be imported here, so that user keeps working without re-enrolling.', '%n accounts configured in the bundled twofactor_totp app can be imported here, so those users keep working without re-enrolling.', totpImportCount) }}</p>
			<NcButton :disabled="importingTotp" @click="importTotp">
				{{ n('twofactor_oath', 'Import %n account from twofactor_totp', 'Import %n accounts from twofactor_totp', totpImportCount) }}
			</NcButton>
		</NcNoteCard>
		<NcNoteCard v-if="totpEnabled && totpDuplicateUsers.length > 0" type="warning" class="otp-admin__banner">
			<p>{{ n('twofactor_oath', '%n user is registered with both twofactor_totp and OATH. OATH already covers everything twofactor_totp does, so their twofactor_totp registration should be removed — otherwise it breaks their login once twofactor_totp is disabled.', '%n users are registered with both twofactor_totp and OATH. OATH already covers everything twofactor_totp does, so their twofactor_totp registration should be removed — otherwise it breaks their login once twofactor_totp is disabled.', totpDuplicateUsers.length) }}</p>
			<p>{{ t('twofactor_oath', 'Because twofactor_totp is a separate app, the recommended way is to run these commands:') }}</p>
			<pre class="otp-admin__occ">{{ totpDisableCommands }}</pre>
			<NcButton :disabled="cleaningTotp" @click="cleanupTotp">
				{{ t('twofactor_oath', 'Or let twofactor_oath remove them now') }}
			</NcButton>
		</NcNoteCard>
		<NcNoteCard v-if="totpEnabled && totpImportCount === 0 && totpDuplicateUsers.length === 0" type="info" class="otp-admin__banner">
			<p>{{ t('twofactor_oath', 'twofactor_totp and twofactor_oath can run side by side, but OATH does everything twofactor_totp does and more, so disabling twofactor_totp is recommended since your users have moved to OATH.') }}</p>
			<p>{{ t('twofactor_oath', 'To retire it cleanly, disable the app and remove its leftover associations:') }}</p>
			<pre class="otp-admin__occ">{{ retireCommands }}</pre>
			<p>{{ t('twofactor_oath', 'The second command belongs with the first: after disabling, the stale associations would otherwise cause a "could not load" error at login.') }}</p>
		</NcNoteCard>

		<div class="otp-admin__field otp-admin__length">
			<NcSelect
				:modelValue="length"
				:options="secretPresetOptions"
				:reduce="(o) => o.value"
				label="label"
				:clearable="false"
				:inputLabel="t('twofactor_oath', 'Default length of generated secrets')"
				@update:modelValue="onLengthChange" />
		</div>

		<h3 class="otp-admin__subhead">
			{{ t('twofactor_oath', 'Admin-managed users (no self-service)') }}
		</h3>
		<p class="otp-admin__hint">
			{{ t('twofactor_oath', 'Members of the managed groups cannot set up or disable OTP themselves; an administrator provisions it for them. Managed and excluded groups are mutually exclusive: set one, or neither. With a managed group, only its members are managed. With an excluded group, everyone except its members is managed. With neither, every user may self-service.') }}
		</p>

		<div class="otp-admin__field">
			<NcSelect
				:modelValue="managedGroups"
				:options="allGroups"
				:multiple="true"
				:reduce="(group) => group.id"
				:disabled="excludedGroups.length > 0"
				label="label"
				:inputLabel="t('twofactor_oath', 'Managed groups')"
				:placeholder="t('twofactor_oath', 'No managed groups')"
				@update:modelValue="onManagedChange" />
		</div>

		<div class="otp-admin__field">
			<NcSelect
				:modelValue="excludedGroups"
				:options="allGroups"
				:multiple="true"
				:reduce="(group) => group.id"
				:disabled="managedGroups.length > 0"
				label="label"
				:inputLabel="t('twofactor_oath', 'Excluded groups')"
				:placeholder="t('twofactor_oath', 'No excluded groups')"
				@update:modelValue="onExcludedChange" />
		</div>

		<h3 class="otp-admin__subhead">
			{{ t('twofactor_oath', 'Bulk provisioning') }}
		</h3>
		<p class="otp-admin__hint">
			{{ t('twofactor_oath', 'Load the managed users. Use "Show" to reveal a user\'s current secret and QR code without changing it. Enter a custom secret (empty = random) and adjust the settings, then provision to create, enable and lock the tokens. Provisioning a user who already has a token replaces and invalidates the old secret.') }}
		</p>

		<div class="otp-admin__actions">
			<NcButton :disabled="loading" @click="loadUsers">
				{{ loaded ? t('twofactor_oath', 'Reload managed users') : t('twofactor_oath', 'Load managed users') }}
			</NcButton>
			<NcButton @click="exportUsers">
				{{ t('twofactor_oath', 'Export as CSV') }}
			</NcButton>
		</div>

		<p v-if="loaded && rows.length === 0" class="otp-admin__hint">
			{{ t('twofactor_oath', 'No managed users. Set a managed or excluded group above.') }}
		</p>

		<details v-if="rows.length" class="otp-admin__import">
			<summary>{{ t('twofactor_oath', 'Import from CSV (paste)') }}</summary>
			<p class="otp-admin__hint">
				{{ t('twofactor_oath', 'Paste rows in the export format. Rows with status "none" or "renew" get checked and their values applied; "enabled"/"disabled" rows are left untouched, and users not listed are unchecked. Then review and provision.') }}
			</p>
			<textarea
				v-model="importText"
				class="otp-admin__import-text"
				rows="6"
				placeholder="username,status,type,algorithm,digits,period,counter,challenge,suite,secret" />
			<NcButton :disabled="importText.trim() === ''" @click="applyCsv">
				{{ t('twofactor_oath', 'Apply pasted CSV') }}
			</NcButton>
		</details>

		<NcCheckboxRadioSwitch
			v-if="rows.length"
			:modelValue="strict"
			type="switch"
			@update:modelValue="setStrict">
			{{ t('twofactor_oath', 'Strict RFC compliance (grey out options the relevant RFC does not cover)') }}
		</NcCheckboxRadioSwitch>

		<div v-if="rows.length" class="otp-admin__toolbar">
			<label class="otp-admin__toolbar-item">
				{{ t('twofactor_oath', 'Sort by') }}
				<select v-model="sortBy">
					<option value="username">{{ t('twofactor_oath', 'User') }}</option>
					<option value="status">{{ t('twofactor_oath', 'Status') }}</option>
					<option value="type">{{ t('twofactor_oath', 'Type') }}</option>
				</select>
			</label>
			<label class="otp-admin__toolbar-item">
				{{ t('twofactor_oath', 'Status') }}
				<select v-model="filterStatus">
					<option value="all">{{ t('twofactor_oath', 'All') }}</option>
					<option value="selected">{{ t('twofactor_oath', 'Selected (to change)') }}</option>
					<option v-for="s in statusValues" :key="s" :value="s">{{ statusLabel(s) }}</option>
				</select>
			</label>
			<label class="otp-admin__toolbar-item">
				{{ t('twofactor_oath', 'Type') }}
				<select v-model="filterType">
					<option value="all">{{ t('twofactor_oath', 'All') }}</option>
					<option value="totp">TOTP</option>
					<option value="hotp">HOTP</option>
					<option value="ocra">OCRA</option>
				</select>
			</label>
			<span class="otp-admin__toolbar-count">{{ visibleRows.length }} / {{ rows.length }}</span>
			<NcButton class="otp-admin__toolbar-item" @click="invertSelection">
				{{ t('twofactor_oath', 'Invert selection') }}
			</NcButton>
		</div>

		<table v-if="rows.length" class="otp-admin__table">
			<thead>
				<tr>
					<th><input type="checkbox" :checked="allSelected" @change="toggleAll($event.target.checked)"></th>
					<th>{{ t('twofactor_oath', 'User') }}</th>
					<th>{{ t('twofactor_oath', 'Status') }}</th>
					<th>{{ t('twofactor_oath', 'Type') }}</th>
					<th>{{ t('twofactor_oath', 'Algorithm') }}</th>
					<th>{{ t('twofactor_oath', 'Digits') }}</th>
					<th>{{ t('twofactor_oath', 'Period') }}</th>
					<th>{{ t('twofactor_oath', 'Counter') }}</th>
					<th>{{ t('twofactor_oath', 'Challenge') }}</th>
					<th>{{ t('twofactor_oath', 'OCRA suite') }}</th>
					<th>{{ t('twofactor_oath', 'Custom secret') }}</th>
					<th />
					<th>{{ t('twofactor_oath', 'Secret / QR') }}</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="row in visibleRows" :key="row.username">
					<td><input type="checkbox" :checked="row.selected" @click="onRowCheckboxClick(row, $event)"></td>
					<td>{{ row.username }}</td>
					<td>{{ row.status }}<span v-if="row.message"> ({{ row.message }})</span></td>
					<td>
						<select v-if="row.selected" v-model="row.type" @change="normalizeRow(row)">
							<option value="totp">
								TOTP
							</option>
							<option value="hotp">
								HOTP
							</option>
							<option value="ocra">
								OCRA
							</option>
						</select>
						<span v-else>{{ row.type.toUpperCase() }}</span>
					</td>
					<td>
						<select v-if="row.selected" v-model="row.algorithm">
							<option v-for="opt in allowedAlgoNames(row)" :key="opt.value" :value="opt.value">
								{{ opt.label }}
							</option>
						</select>
						<span v-else>{{ algoLabel(row.algorithm) }}</span>
					</td>
					<td>
						<select v-if="row.selected" v-model.number="row.digits" class="otp-admin__num">
							<option v-for="d in digitOptions(row)" :key="d" :value="d">
								{{ d }}
							</option>
						</select>
						<span v-else>{{ row.digits }}</span>
					</td>
					<td>
						<template v-if="row.type === 'totp'">
							<select v-if="row.selected" v-model.number="row.period" class="otp-admin__num">
								<option v-for="seconds in PERIOD_VALUES" :key="seconds" :value="seconds">
									{{ periodLabel(seconds) }}
								</option>
							</select>
							<span v-else>{{ periodLabel(row.period) }}</span>
						</template>
						<span v-else>—</span>
					</td>
					<td>
						<template v-if="row.type === 'hotp'">
							<input
								v-if="row.selected"
								v-model.number="row.counter"
								type="number"
								:min="0"
								class="otp-admin__num">
							<span v-else>{{ row.counter }}</span>
						</template>
						<span v-else>—</span>
					</td>
					<td>
						<template v-if="row.type === 'ocra'">
							<select v-if="row.selected" v-model.number="row.challenge" class="otp-admin__num">
								<option v-for="n in challengeRange" :key="n" :value="n">
									{{ n }}
								</option>
							</select>
							<span v-else>{{ row.challenge }}</span>
						</template>
						<span v-else>—</span>
					</td>
					<td>
						<code v-if="row.type === 'ocra'" class="otp-admin__suite">{{ rowSuite(row) }}</code>
						<span v-else>—</span>
					</td>
					<td>
						<template v-if="row.selected">
							<input
								v-model="row.input"
								type="text"
								:placeholder="t('twofactor_oath', 'random')"
								class="otp-admin__secret"
								:class="{ 'otp-admin__secret--error': secretError(row) !== '' }">
							<div v-if="secretError(row)" class="otp-admin__secret-err">
								{{ secretError(row) }}
							</div>
						</template>
						<span v-else>—</span>
					</td>
					<td>
						<NcButton v-if="hasToken(row) || row.secret || row.uri" @click="toggleRow(row)">
							{{ (row.secret || row.uri) ? t('twofactor_oath', 'Hide') : t('twofactor_oath', 'Show') }}
						</NcButton>
					</td>
					<td>
						<div v-if="row.uri || row.secret" class="otp-admin__result">
							<Qrcode v-if="row.uri" :value="row.uri" :options="{ width: 120 }" />
							<div class="otp-admin__secret-line">
								<code class="otp-admin__shown-secret">{{ row.secret }}</code>
								<NcButton
									variant="tertiary"
									:title="t('twofactor_oath', 'Copy secret')"
									:aria-label="t('twofactor_oath', 'Copy secret')"
									@click="copySecret(row.secret)">
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
						</div>
					</td>
				</tr>
			</tbody>
		</table>

		<div v-if="rows.length" class="otp-admin__actions">
			<NcButton variant="primary" :disabled="provisioning || selectedCount === 0" @click="provision">
				{{ t('twofactor_oath', 'Provision selected tokens') }} ({{ selectedCount }})
			</NcButton>
			<NcButton variant="error" :disabled="provisioning || selectedCount === 0" @click="disableSelected">
				{{ t('twofactor_oath', 'Disable selected tokens') }} ({{ selectedCount }})
			</NcButton>
			<NcButton @click="hideUsers">
				{{ t('twofactor_oath', 'Hide managed users') }}
			</NcButton>
		</div>

		<NcDialog
			v-if="exportDialogOpen"
			:open="exportDialogOpen"
			:name="t('twofactor_oath', 'Export as CSV')"
			@update:open="exportDialogOpen = $event">
			{{ t('twofactor_oath', 'Include the existing secrets in the export? They are written in plaintext, so store the file securely.') }}
			<template #actions>
				<NcButton variant="error" @click="doExport(true)">
					{{ t('twofactor_oath', 'Include secrets') }}
				</NcButton>
				<NcButton variant="primary" @click="doExport(false)">
					{{ t('twofactor_oath', 'Without secrets') }}
				</NcButton>
			</template>
		</NcDialog>

		<NcDialog
			v-if="confirmDialog.open"
			:open="confirmDialog.open"
			:name="confirmDialog.title"
			@update:open="resolveConfirm(false)">
			{{ confirmDialog.message }}
			<template #actions>
				<NcButton @click="resolveConfirm(false)">
					{{ t('twofactor_oath', 'Cancel') }}
				</NcButton>
				<NcButton :variant="confirmDialog.destructive ? 'error' : 'primary'" @click="resolveConfirm(true)">
					{{ t('twofactor_oath', 'Confirm') }}
				</NcButton>
			</template>
		</NcDialog>
	</NcSettingsSection>
</template>

<script>
import Qrcode from '@chenfengyuan/vue-qrcode'
import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import { NcButton, NcCheckboxRadioSwitch, NcDialog, NcNoteCard, NcSelect, NcSettingsSection } from '@nextcloud/vue'
import { ALGORITHM_NAME_OPTIONS, base32Length, customSecretIssue, DIGITS_MAX, DIGITS_MIN, ocraChallengeLength, PERIOD_VALUES, periodLabel, SECRET_PRESETS, strictAllowedAlgorithms, strictMinDigits, TYPE } from '../constants.js'

// Algorithm code -> CSV/name representation (matches Constants::ALGORITHM_DIGESTS).
const CODE_NAME = { 1: 'sha1', 2: 'sha224', 3: 'sha256', 4: 'sha384', 5: 'sha512' }

export default {
	name: 'AdminSettings',
	components: {
		Qrcode,
		NcButton,
		NcCheckboxRadioSwitch,
		NcDialog,
		NcNoteCard,
		NcSelect,
		NcSettingsSection,
	},

	setup() {
		return { ALGORITHM_NAME_OPTIONS, PERIOD_VALUES, periodLabel }
	},

	data() {
		return {
			length: loadState('twofactor_oath', 'secret_length'),
			allGroups: loadState('twofactor_oath', 'all_groups'),
			managedGroups: loadState('twofactor_oath', 'managed_groups'),
			excludedGroups: loadState('twofactor_oath', 'excluded_groups'),
			strict: true,
			loading: false,
			loaded: false,
			provisioning: false,
			rows: [],
			sortBy: 'username',
			filterStatus: 'all',
			filterType: 'all',
			importText: '',
			exportDialogOpen: false,
			confirmDialog: { open: false, title: '', message: '', destructive: false, resolve: null },
			lastSelectedUsername: null,
			totpImportCount: loadState('twofactor_oath', 'totp_import_count', 0),
			totpEnabled: loadState('twofactor_oath', 'totp_enabled', false),
			totpDuplicateUsers: loadState('twofactor_oath', 'totp_duplicate_users', []),
			importingTotp: false,
			cleaningTotp: false,
		}
	},

	computed: {
		// occ commands an admin can run manually to deregister twofactor_totp for
		// the users registered with both apps (one per user).
		totpDisableCommands() {
			return this.totpDuplicateUsers.map((uid) => `occ twofactorauth:disable ${uid} totp`).join('\n')
		},

		// Commands to fully retire twofactor_totp once everyone has moved to OATH:
		// disable the app, then remove the stale provider associations.
		retireCommands() {
			return 'occ app:disable twofactor_totp\nocc twofactorauth:cleanup totp'
		},

		secretPresetOptions() {
			const names = {
				minimal: t('twofactor_oath', 'Minimal'),
				recommended: t('twofactor_oath', 'Recommended'),
				high: t('twofactor_oath', 'High'),
				extreme: t('twofactor_oath', 'Extreme'),
				paranoia: t('twofactor_oath', 'Paranoia'),
			}
			return SECRET_PRESETS.map((p) => ({
				value: p.bytes,
				label: t('twofactor_oath', '{name} — {bytes} byte ({bits} bit, {chars} characters)', { name: names[p.key] || p.key, bytes: p.bytes, bits: p.bytes * 8, chars: base32Length(p.bytes) }),
			}))
		},

		challengeRange() {
			const range = []
			for (let n = DIGITS_MIN; n <= DIGITS_MAX; n++) {
				range.push(n)
			}
			return range
		},

		selectedCount() {
			return this.rows.filter((row) => row.selected).length
		},

		// Distinct statuses present, ordered, for the status filter dropdown.
		statusValues() {
			return [...new Set(this.rows.map((row) => row.status))].sort((a, b) => this.statusRank(a) - this.statusRank(b))
		},

		// Rows after the active filter + sort; the table renders these.
		visibleRows() {
			let list = this.rows
			if (this.filterStatus === 'selected') {
				list = list.filter((row) => row.selected)
			} else if (this.filterStatus !== 'all') {
				list = list.filter((row) => row.status === this.filterStatus)
			}
			if (this.filterType !== 'all') {
				list = list.filter((row) => row.type === this.filterType)
			}
			const byName = (a, b) => a.username.localeCompare(b.username)
			return [...list].sort((a, b) => {
				if (this.sortBy === 'status') {
					return this.statusRank(a.status) - this.statusRank(b.status) || byName(a, b)
				}
				if (this.sortBy === 'type') {
					return this.typeRank(a.type) - this.typeRank(b.type) || byName(a, b)
				}
				return byName(a, b)
			})
		},

		allSelected() {
			return this.visibleRows.length > 0 && this.visibleRows.every((row) => row.selected)
		},
	},

	methods: {
		hasToken(row) {
			// 'provisioned' is the transient status of a token just created this
			// session (before reload); like 'enabled'/'pending' it means the user
			// has a token, so re-provisioning it must trigger the replace warning.
			return row.status === 'enabled' || row.status === 'pending' || row.status === 'provisioned'
		},

		toggleAll(checked) {
			this.visibleRows.forEach((row) => {
				row.selected = checked
			})
		},

		// Flip the selection of every currently visible row (respects the filter).
		invertSelection() {
			this.visibleRows.forEach((row) => {
				row.selected = !row.selected
			})
		},

		// Row checkbox click. Plain click toggles one row; Shift+click selects the
		// whole range from the last clicked row to this one (the standard range
		// selection convention), setting them all to the clicked row's new state.
		onRowCheckboxClick(row, event) {
			const checked = event.target.checked
			row.selected = checked
			const list = this.visibleRows
			if (event.shiftKey && this.lastSelectedUsername !== null) {
				const a = list.findIndex((r) => r.username === this.lastSelectedUsername)
				const b = list.findIndex((r) => r.username === row.username)
				if (a !== -1 && b !== -1) {
					const [lo, hi] = a < b ? [a, b] : [b, a]
					for (let i = lo; i <= hi; i++) {
						list[i].selected = checked
					}
				}
			}
			this.lastSelectedUsername = row.username
		},

		statusRank(status) {
			return ['none', 'renew', 'pending', 'enabled', 'provisioned', 'disabled', 'error'].indexOf(status) + 1 || 99
		},

		typeRank(type) {
			return ['totp', 'hotp', 'ocra'].indexOf(type)
		},

		statusLabel(status) {
			const labels = {
				none: t('twofactor_oath', 'Without token'),
				pending: t('twofactor_oath', 'Pending'),
				enabled: t('twofactor_oath', 'Enabled'),
				renew: t('twofactor_oath', 'Renew'),
				disabled: t('twofactor_oath', 'Disabled'),
				provisioned: t('twofactor_oath', 'Provisioned'),
				error: t('twofactor_oath', 'Error'),
			}
			return labels[status] || status
		},

		secretError(row) {
			const issue = customSecretIssue(row.input)
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

		typeCode(row) {
			return row.type === 'totp' ? TYPE.TOTP : row.type === 'hotp' ? TYPE.HOTP : TYPE.OCRA
		},

		allowedAlgoNames(row) {
			const codes = this.strict ? strictAllowedAlgorithms(this.typeCode(row)) : [1, 2, 3, 4, 5]
			const names = codes.map((c) => CODE_NAME[c])
			// Always keep the row's current value selectable (e.g. a non-strict value
			// imported from CSV), so strict only blocks switching TO a new odd value.
			if (!names.includes(row.algorithm)) {
				names.push(row.algorithm)
			}
			return ALGORITHM_NAME_OPTIONS.filter((o) => names.includes(o.value))
		},

		digitOptions(row) {
			const min = this.strict ? strictMinDigits(this.typeCode(row)) : DIGITS_MIN
			const options = []
			for (let d = min; d <= DIGITS_MAX; d++) {
				options.push(d)
			}
			if (!options.includes(row.digits)) {
				options.push(row.digits)
				options.sort((a, b) => a - b)
			}
			return options
		},

		rowSuite(row) {
			const qn = `QN${String(row.challenge).padStart(2, '0')}`
			return `OCRA-1:HOTP-${row.algorithm.toUpperCase()}-${row.digits}:${qn}`
		},

		// Keep a row's algorithm + digits within the allowed set (strict + type).
		normalizeRow(row) {
			const names = this.allowedAlgoNames(row).map((o) => o.value)
			if (!names.includes(row.algorithm)) {
				row.algorithm = names[0]
			}
			const min = this.strict ? strictMinDigits(this.typeCode(row)) : DIGITS_MIN
			if (row.digits < min) {
				row.digits = min
			}
		},

		setStrict(value) {
			this.strict = value
			this.rows.forEach((row) => this.normalizeRow(row))
		},

		// Parse pasted CSV (export format) into row patches; suite is ignored
		// (recomputed from algorithm + digits + challenge), epoch no longer exists.
		parseCsv(text) {
			const out = []
			for (const line of text.split(/\r\n|\r|\n/)) {
				const l = line.trim()
				if (l === '') {
					continue
				}
				const c = l.split(',')
				const username = (c[0] || '').trim()
				if (username === '' || username.toLowerCase() === 'username') {
					continue
				}
				const type = (c[2] || '').trim().toLowerCase()
				if (!['totp', 'hotp', 'ocra'].includes(type)) {
					continue
				}
				out.push({
					username,
					status: (c[1] || '').trim().toLowerCase(),
					type,
					algorithm: (c[3] || 'sha1').trim().toLowerCase(),
					digits: Number((c[4] || '').trim()) || 6,
					period: Number((c[5] || '').trim()) || 30,
					counter: Number((c[6] || '').trim()) || 0,
					challenge: Number((c[7] || '').trim()) || 8,
					secret: (c[9] || '').trim(),
				})
			}
			return out
		},

		// Merge pasted CSV into the loaded list: 'none'/'renew' rows get the values
		// applied and are checked; all other rows are left unchecked and untouched.
		applyCsv() {
			const entries = this.parseCsv(this.importText)
			this.rows.forEach((row) => {
				row.selected = false
			})
			let applied = 0
			const skipped = []
			for (const e of entries) {
				const row = this.rows.find((r) => r.username === e.username)
				if (!row) {
					skipped.push(e.username)
					continue
				}
				if (e.status === 'none' || e.status === 'renew') {
					row.type = e.type
					row.algorithm = e.algorithm
					row.digits = e.digits
					row.period = e.period
					row.counter = e.counter
					row.challenge = e.challenge
					row.input = e.secret
					row.selected = true
					applied++
				}
			}
			OC.Notification.showTemporary(n('twofactor_oath', '%n row applied.', '%n rows applied.', applied))
			if (skipped.length) {
				// Build the list separately: a quoted string inside the n() call
				// would confuse the translation extractor's regex.
				const list = skipped.join(', ')
				OC.Notification.showTemporary(n('twofactor_oath', '%n row skipped (not managed users): {list}', '%n rows skipped (not managed users): {list}', skipped.length, { list }))
			}
			// Show exactly the rows that will be changed.
			this.filterStatus = 'selected'
		},

		async importTotp() {
			this.importingTotp = true
			try {
				const resp = await axios.post(generateUrl('/apps/twofactor_oath/admin/import-totp'), {})
				const { imported, skipped } = resp.data
				OC.Notification.showTemporary(t('twofactor_oath', 'Imported {imported}, skipped {skipped}.', { imported, skipped }))
				if (this.loaded) {
					this.loadUsers()
				} else {
					this.refreshTotpStatus()
				}
			} catch {
				OC.Notification.showTemporary(t('twofactor_oath', 'Could not import from twofactor_totp'))
			} finally {
				this.importingTotp = false
			}
		},

		// Button action of the cleanup banner: deregister twofactor_totp for the
		// users registered with both apps, after an explicit confirmation.
		async cleanupTotp() {
			const confirmed = await this.confirm({
				title: t('twofactor_oath', 'Remove twofactor_totp registrations?'),
				message: n('twofactor_oath', 'This removes the twofactor_totp registration and secret of %n user who already has an OATH token. This cannot be undone.', 'This removes the twofactor_totp registration and secret of %n users who already have an OATH token. This cannot be undone.', this.totpDuplicateUsers.length),
				destructive: true,
			})
			if (!confirmed) {
				return
			}
			this.cleaningTotp = true
			try {
				const resp = await axios.post(generateUrl('/apps/twofactor_oath/admin/cleanup-totp-duplicates'), {})
				OC.Notification.showTemporary(n('twofactor_oath', '%n twofactor_totp registration removed.', '%n twofactor_totp registrations removed.', resp.data.cleaned))
				if (this.loaded) {
					this.loadUsers()
				} else {
					this.refreshTotpStatus()
				}
			} catch {
				OC.Notification.showTemporary(t('twofactor_oath', 'Could not remove twofactor_totp registrations'))
			} finally {
				this.cleaningTotp = false
			}
		},

		async copySecret(secret) {
			try {
				await navigator.clipboard.writeText(secret)
				OC.Notification.showTemporary(t('twofactor_oath', 'Secret copied to clipboard'))
			} catch {
				OC.Notification.showTemporary(t('twofactor_oath', 'Could not copy the secret'))
			}
		},

		onLengthChange(value) {
			this.length = value
			this.post('/apps/twofactor_oath/admin/secret-length', { length: value }, t('twofactor_oath', 'Could not save the secret length'))
		},

		onManagedChange(value) {
			this.managedGroups = value
			this.post('/apps/twofactor_oath/admin/managed-groups', { groups: value }, t('twofactor_oath', 'Could not save the managed groups'))
		},

		onExcludedChange(value) {
			this.excludedGroups = value
			this.post('/apps/twofactor_oath/admin/excluded-groups', { groups: value }, t('twofactor_oath', 'Could not save the excluded groups'))
		},

		async loadUsers() {
			this.loading = true
			try {
				const resp = await axios.get(generateUrl('/apps/twofactor_oath/admin/managed-users'))
				this.rows = resp.data.users.map((user) => ({ ...user, challenge: ocraChallengeLength(user.suite), input: '', secret: '', uri: null, message: '', selected: user.status === 'none' }))
				this.loaded = true
				this.refreshTotpStatus()
			} catch {
				OC.Notification.showTemporary(t('twofactor_oath', 'Could not load the managed users'))
			} finally {
				this.loading = false
			}
		},

		// Toggle the revealed secret/QR for a row.
		toggleRow(row) {
			if (row.secret || row.uri) {
				row.secret = ''
				row.uri = null
			} else {
				this.showRow(row)
			}
		},

		async showRow(row) {
			try {
				const resp = await axios.get(generateUrl('/apps/twofactor_oath/admin/show-token'), { params: { username: row.username } })
				row.secret = resp.data.secret
				row.uri = resp.data.uri
			} catch {
				OC.Notification.showTemporary(t('twofactor_oath', 'Could not load the token'))
			}
		},

		exportUsers() {
			// Ask whether to include the plaintext secrets (own dialog: the safe
			// option is the highlighted default, including them is marked destructive).
			this.exportDialogOpen = true
		},

		doExport(withSecrets) {
			this.exportDialogOpen = false
			const url = generateUrl('/apps/twofactor_oath/admin/export-users')
			window.location.href = withSecrets ? `${url}?secrets=1` : url
		},

		// Promise-based confirmation backed by NcDialog (replaces the deprecated
		// OC.dialogs.confirm). Resolves true on confirm, false on cancel or dismiss.
		confirm({ title, message, destructive = false }) {
			return new Promise((resolve) => {
				this.confirmDialog = { open: true, title, message, destructive, resolve }
			})
		},

		resolveConfirm(decision) {
			const { resolve } = this.confirmDialog
			this.confirmDialog.open = false
			this.confirmDialog.resolve = null
			resolve?.(decision)
		},

		// Close the whole list; the top button reverts to "Load managed users".
		hideUsers() {
			this.rows = []
			this.loaded = false
		},

		// Re-read the twofactor_totp migration state so the banners reflect the
		// current reality after any change (import / disable / provision / cleanup).
		async refreshTotpStatus() {
			try {
				const resp = await axios.get(generateUrl('/apps/twofactor_oath/admin/totp-status'))
				this.totpEnabled = resp.data.enabled
				this.totpImportCount = resp.data.importCount
				this.totpDuplicateUsers = resp.data.duplicateUsers
			} catch {
				// Keep the current values on error.
			}
		},

		algoLabel(value) {
			const opt = ALGORITHM_NAME_OPTIONS.find((o) => o.value === value)
			return opt ? opt.label : value
		},

		async provision() {
			const chosen = this.rows.filter((row) => row.selected)
			if (chosen.length === 0) {
				return
			}
			if (chosen.some((row) => customSecretIssue(row.input) !== null)) {
				OC.Notification.showTemporary(t('twofactor_oath', 'Some selected rows have an invalid custom secret.'))
				return
			}
			const existing = chosen.filter((row) => this.hasToken(row))
			if (existing.length > 0 && !(await this.confirmReplace(existing.length))) {
				return
			}

			this.provisioning = true
			const header = 'username,status,type,algorithm,digits,period,counter,challenge,suite,secret'
			const lines = chosen.map((r) => [r.username, r.status, r.type, r.algorithm, r.digits, r.period, r.counter, r.type === 'ocra' ? r.challenge : '', r.type === 'ocra' ? this.rowSuite(r) : '', r.input].join(','))
			try {
				const resp = await axios.post(generateUrl('/apps/twofactor_oath/admin/provision'), { data: [header, ...lines].join('\n') })
				for (const result of resp.data.results) {
					const row = this.rows.find((r) => r.username === result.username)
					if (row) {
						row.status = result.status
						row.message = result.message || ''
						row.secret = result.secret || ''
						row.uri = result.uri || null
					}
				}
				this.refreshTotpStatus()
			} catch {
				OC.Notification.showTemporary(t('twofactor_oath', 'Provisioning failed'))
			} finally {
				this.provisioning = false
			}
		},

		confirmReplace(count) {
			return this.confirm({
				title: t('twofactor_oath', 'Replace existing tokens?'),
				message: n('twofactor_oath', '%n user already has a token. Provisioning replaces and INVALIDATES their current secret — they will be locked out until they set up the new one. Use "Show" instead to view a current secret without changing it. Continue?', '%n users already have a token. Provisioning replaces and INVALIDATES their current secret — they will be locked out until they set up the new one. Use "Show" instead to view a current secret without changing it. Continue?', count),
				destructive: true,
			})
		},

		// Disable OATH for the ticked users: delete their secret and remove the
		// provider registration (same effect as `occ twofactorauth:disable <uid> oath`).
		async disableSelected() {
			const chosen = this.rows.filter((row) => row.selected)
			if (chosen.length === 0) {
				return
			}
			const confirmed = await this.confirm({
				title: t('twofactor_oath', 'Disable selected tokens?'),
				message: n('twofactor_oath', 'Disable OATH for %n selected user? If you continue, their secret will be deleted and the registration removed. This cannot be undone.', 'Disable OATH for %n selected users? If you continue, their secret will be deleted and the registration removed. This cannot be undone.', chosen.length),
				destructive: true,
			})
			if (!confirmed) {
				return
			}
			this.provisioning = true
			try {
				const resp = await axios.post(generateUrl('/apps/twofactor_oath/admin/disable-users'), { users: chosen.map((r) => r.username) })
				for (const result of resp.data.results) {
					const row = this.rows.find((r) => r.username === result.username)
					if (row && result.status !== 'error') {
						row.status = 'none'
						row.secret = ''
						row.uri = null
						row.message = ''
					}
				}
				OC.Notification.showTemporary(t('twofactor_oath', 'Selected tokens disabled.'))
				this.refreshTotpStatus()
			} catch {
				OC.Notification.showTemporary(t('twofactor_oath', 'Could not disable the selected tokens'))
			} finally {
				this.provisioning = false
			}
		},

		async post(url, data, errorMessage) {
			try {
				await axios.post(generateUrl(url), data)
			} catch {
				OC.Notification.showTemporary(errorMessage)
			}
		},
	},
}
</script>

<style scoped>
/* Full-width section + closing separator (NcSettingsSection caps width at 900px). */
.otp-admin-section {
	width: auto !important;
	border-bottom: 1px solid var(--color-border);
}

.otp-admin__field {
	max-width: 600px;
	margin-bottom: 12px;
}

/* Wide enough that the preset labels are not truncated in the dropdown. */
.otp-admin__length {
	width: 28em;
	max-width: 100%;
}

.otp-admin__length :deep(.v-select),
.otp-admin__length :deep(.vs__dropdown-menu) {
	min-width: 28em;
}

.otp-admin__subhead {
	margin-top: 16px;
	font-weight: bold;
}

.otp-admin__hint {
	max-width: 600px;
	margin-bottom: 8px;
	color: var(--color-text-maxcontrast);
}

.otp-admin__actions {
	display: flex;
	gap: 8px;
	flex-wrap: wrap;
	margin-bottom: 12px;
}

/* Cap banner prose at a readable width; the table below stays full-width. */
.otp-admin__banner {
	max-width: 900px;
}

.otp-admin__occ {
	margin: 8px 0;
	padding: 8px;
	max-width: 100%;
	overflow-x: auto;
	font-family: monospace;
	font-size: 0.9em;
	white-space: pre;
	background-color: var(--color-background-dark);
	border-radius: var(--border-radius);
}

.otp-admin__import {
	margin-bottom: 12px;
}

.otp-admin__import-text {
	display: block;
	width: 100%;
	max-width: 700px;
	margin: 8px 0;
	font-family: monospace;
	resize: vertical;
}

.otp-admin__toolbar {
	display: flex;
	gap: 16px;
	align-items: center;
	flex-wrap: wrap;
	margin-bottom: 12px;
}

.otp-admin__toolbar-item {
	display: flex;
	gap: 6px;
	align-items: center;
}

.otp-admin__toolbar-item select {
	cursor: pointer;
}

.otp-admin__toolbar-count {
	color: var(--color-text-maxcontrast);
}

.otp-admin__table {
	margin-bottom: 12px;
	border-collapse: collapse;
}

.otp-admin__table th,
.otp-admin__table td {
	padding: 4px 8px;
	text-align: start;
	vertical-align: middle;
	border-bottom: 1px solid var(--color-border);
}

.otp-admin__table input[type="checkbox"] {
	cursor: pointer;
}

.otp-admin__table select,
.otp-admin__table select:hover,
.otp-admin__table select:focus {
	cursor: pointer;
}

.otp-admin__num {
	width: 5em;
}

.otp-admin__suite {
	width: 16em;
}

.otp-admin__secret {
	width: 14em;
	font-family: monospace;
}

.otp-admin__secret--error {
	border-color: var(--color-text-error, var(--color-error)) !important;
	box-shadow: 0 0 0 2px var(--color-text-error, var(--color-error));
}

.otp-admin__secret-err {
	max-width: 16em;
	margin-top: 2px;
	color: var(--color-text-error, var(--color-error));
	font-size: 0.85em;
}

.otp-admin__secret-line {
	display: flex;
	align-items: center;
	gap: 4px;
}

.otp-admin__result {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.otp-admin__shown-secret {
	max-width: 220px;
	overflow-x: auto;
	white-space: nowrap;
	font-size: 0.85em;
}
</style>
