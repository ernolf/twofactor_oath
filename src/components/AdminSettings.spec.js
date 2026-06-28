/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { loadState } from '@nextcloud/initial-state'
import { shallowMount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import AdminSettings from './AdminSettings.vue'

vi.mock('@nextcloud/initial-state', () => ({ loadState: vi.fn() }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (url) => url }))
vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }))
vi.mock('@chenfengyuan/vue-qrcode', () => ({ default: { name: 'Qrcode', render: () => null } }))
vi.mock('@nextcloud/vue', () => ({
	NcButton: { name: 'NcButton', template: '<button><slot /></button>' },
	NcCheckboxRadioSwitch: { name: 'NcCheckboxRadioSwitch', template: '<label><slot /></label>' },
	NcDialog: { name: 'NcDialog', template: '<div><slot /></div>' },
	NcNoteCard: { name: 'NcNoteCard', template: '<div><slot /></div>' },
	NcSelect: { name: 'NcSelect', template: '<div />' },
	NcSettingsSection: { name: 'NcSettingsSection', template: '<div><slot /></div>' },
}))

const tMock = (app, text) => text
const nMock = (app, singular, plural, count) => (count === 1 ? singular : plural)
const mountOptions = { global: { mocks: { t: tMock, n: nMock } } }

// loadState feeds the component's initial data(); groups must be arrays so the
// template's length checks do not throw on mount.
const stateValues = {
	secret_length: 20,
	all_groups: [],
	managed_groups: [],
	excluded_groups: [],
	totp_import_count: 0,
	totp_enabled: false,
	totp_duplicate_users: [],
}

const row = (over = {}) => ({ username: 'u', status: 'none', type: 'totp', algorithm: 'sha1', digits: 6, period: 30, counter: 0, challenge: 8, selected: false, ...over })

beforeEach(() => {
	vi.clearAllMocks()
	loadState.mockImplementation((app, key, def) => (key in stateValues ? stateValues[key] : def))
	globalThis.t = tMock
	globalThis.n = nMock
	globalThis.OC = { Notification: { showTemporary: vi.fn() }, dialogs: { confirm: vi.fn() } }
})

describe('AdminSettings', () => {
	it('builds the OCRA suite for a row', () => {
		const wrapper = shallowMount(AdminSettings, mountOptions)
		expect(wrapper.vm.rowSuite({ challenge: 8, algorithm: 'sha256', digits: 8 })).toBe('OCRA-1:HOTP-SHA256-8:QN08')
	})

	it('parses pasted CSV and skips the header and unmanaged types', () => {
		const wrapper = shallowMount(AdminSettings, mountOptions)
		const csv = [
			'username,status,type,algorithm,digits,period,counter,challenge,suite,secret',
			'alice,none,totp,sha1,6,30,0,,,',
			'bob,none,bogus,sha1,6,30,0,,,',
		].join('\n')

		const parsed = wrapper.vm.parseCsv(csv)

		expect(parsed).toHaveLength(1)
		expect(parsed[0]).toMatchObject({ username: 'alice', type: 'totp' })
	})

	it('filters the visible rows by selection', async () => {
		const wrapper = shallowMount(AdminSettings, mountOptions)
		wrapper.vm.rows = [row({ username: 'a', selected: true }), row({ username: 'b', selected: false })]
		wrapper.vm.filterStatus = 'selected'
		await wrapper.vm.$nextTick()

		expect(wrapper.vm.visibleRows.map((r) => r.username)).toEqual(['a'])
		expect(wrapper.vm.selectedCount).toBe(1)
	})

	it('inverts the selection of the visible rows', async () => {
		const wrapper = shallowMount(AdminSettings, mountOptions)
		wrapper.vm.rows = [row({ username: 'a', selected: true }), row({ username: 'b', selected: false })]
		await wrapper.vm.$nextTick()

		wrapper.vm.invertSelection()

		expect(wrapper.vm.rows.map((r) => r.selected)).toEqual([false, true])
	})

	it('labels known statuses and algorithms', () => {
		const wrapper = shallowMount(AdminSettings, mountOptions)
		expect(wrapper.vm.statusLabel('enabled')).toBe('Enabled')
		expect(wrapper.vm.algoLabel('sha256')).toBe('SHA-256')
	})
})
