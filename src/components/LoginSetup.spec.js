/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { loadState } from '@nextcloud/initial-state'
import { flushPromises, shallowMount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import LoginSetup from './LoginSetup.vue'
import { STATE } from '../constants.js'
import { saveState } from '../services/StateService.js'

vi.mock('@nextcloud/initial-state', () => ({ loadState: vi.fn(() => false) }))
vi.mock('@nextcloud/router', () => ({ imagePath: () => '/img/app-dark.svg' }))
vi.mock('../logger.js', () => ({ default: { error: vi.fn(), warn: vi.fn(), debug: vi.fn(), info: vi.fn(), fatal: vi.fn() } }))
vi.mock('../services/StateService.js', () => ({ saveState: vi.fn() }))
vi.mock('@chenfengyuan/vue-qrcode', () => ({ default: { name: 'Qrcode', render: () => null } }))
vi.mock('@nextcloud/vue', () => ({
	NcButton: { name: 'NcButton', template: '<button><slot /></button>' },
	NcCheckboxRadioSwitch: { name: 'NcCheckboxRadioSwitch', template: '<label><slot /></label>' },
	NcNoteCard: { name: 'NcNoteCard', template: '<div><slot /></div>' },
	NcTextField: { name: 'NcTextField', template: '<input>' },
}))

const tMock = (app, text) => text
const mountOptions = { global: { mocks: { t: tMock } } }

beforeEach(() => {
	vi.clearAllMocks()
	loadState.mockReturnValue(false)
	// saveState answers the generate call (CREATED) with a secret, and the confirm
	// call (ENABLED) with the enabled state.
	saveState.mockImplementation(async (data) => (data.state === STATE.ENABLED
		? { state: STATE.ENABLED }
		: { secret: 'JBSWY3DPEHPK3PXP', qrUrl: 'otpauth://totp/x', challenge: '' }))
	globalThis.t = tMock
	globalThis.OC = { Notification: { showTemporary: vi.fn() } }
})

describe('LoginSetup', () => {
	it('generates a secret on mount', async () => {
		const wrapper = shallowMount(LoginSetup, mountOptions)
		await flushPromises()

		expect(saveState).toHaveBeenCalledWith(expect.objectContaining({ state: STATE.CREATED }))
		expect(wrapper.vm.secret).toBe('JBSWY3DPEHPK3PXP')
		expect(wrapper.vm.loading).toBe(false)
	})

	it('submits the login form when the code is accepted', async () => {
		const wrapper = shallowMount(LoginSetup, mountOptions)
		await flushPromises()
		const submit = vi.fn()
		wrapper.vm.$refs.confirmForm.submit = submit

		await wrapper.vm.confirm('755224')
		await flushPromises()

		expect(saveState).toHaveBeenCalledWith(expect.objectContaining({ state: STATE.ENABLED, code: '755224' }))
		expect(submit).toHaveBeenCalled()
	})

	it('shows the managed note and skips generation for admin-managed tokens', async () => {
		loadState.mockReturnValue(true)
		const wrapper = shallowMount(LoginSetup, mountOptions)
		await flushPromises()

		expect(wrapper.vm.managed).toBe(true)
		expect(saveState).not.toHaveBeenCalled()
		expect(wrapper.findComponent({ name: 'NcNoteCard' }).exists()).toBe(true)
	})
})
