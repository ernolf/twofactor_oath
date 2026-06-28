/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { loadState } from '@nextcloud/initial-state'
import { confirmPassword } from '@nextcloud/password-confirmation'
import { flushPromises, shallowMount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import PersonalOtpSettings from './PersonalOtpSettings.vue'
import { STATE, TYPE } from '../constants.js'

// A hoisted holder lets each test swap in a fresh fake store; vi.mock factories
// run before module init and cannot close over a plain top-level binding.
const { storeRef } = vi.hoisted(() => ({ storeRef: { current: null } }))

vi.mock('../store.js', () => ({ useOtpStore: () => storeRef.current }))
vi.mock('@nextcloud/password-confirmation', () => ({ confirmPassword: vi.fn() }))
vi.mock('@nextcloud/initial-state', () => ({ loadState: vi.fn(() => false) }))
vi.mock('@nextcloud/router', () => ({ imagePath: () => '/img/app-dark.svg' }))
vi.mock('../logger.js', () => ({ default: { error: vi.fn(), warn: vi.fn(), debug: vi.fn(), info: vi.fn(), fatal: vi.fn() } }))
vi.mock('../services/StateService.js', () => ({
	getOtpConfig: vi.fn(),
	resyncOtp: vi.fn(),
	showOtp: vi.fn(),
	deactivateOtp: vi.fn(),
}))
vi.mock('@chenfengyuan/vue-qrcode', () => ({ default: { name: 'Qrcode', render: () => null } }))
vi.mock('@nextcloud/vue', () => ({
	NcButton: { name: 'NcButton', template: '<button><slot /></button>' },
	NcCheckboxRadioSwitch: { name: 'NcCheckboxRadioSwitch', template: '<label><slot /></label>' },
	NcNoteCard: { name: 'NcNoteCard', template: '<div><slot /></div>' },
	NcTextField: { name: 'NcTextField', template: '<input>' },
}))

// Nextcloud injects t/n as runtime globals: the component template resolves them
// via globalProperties, while its methods call the bare t()/n() functions.
const tMock = (app, text) => text
const nMock = (app, singular, plural, count) => (count === 1 ? singular : plural)
const mountOptions = { global: { mocks: { t: tMock, n: nMock } } }

function makeStore(overrides = {}) {
	return {
		otpState: STATE.DISABLED,
		enable: vi.fn().mockResolvedValue({ secret: 'JBSWY3DPEHPK3PXP', qrUrl: 'otpauth://totp/x', challenge: '' }),
		confirm: vi.fn(),
		...overrides,
	}
}

beforeEach(() => {
	vi.clearAllMocks()
	storeRef.current = makeStore()
	confirmPassword.mockResolvedValue()
	loadState.mockReturnValue(false)
	globalThis.t = tMock
	globalThis.n = nMock
	globalThis.OC = { Notification: { showTemporary: vi.fn() } }
})

describe('PersonalOtpSettings — enrollment', () => {
	it('starts setup when the switch is turned on', async () => {
		const wrapper = shallowMount(PersonalOtpSettings, mountOptions)

		await wrapper.vm.toggle(true)
		await flushPromises()

		expect(confirmPassword).toHaveBeenCalledOnce()
		expect(storeRef.current.enable).toHaveBeenCalledOnce()
		expect(storeRef.current.enable.mock.calls[0][0]).toMatchObject({ type: TYPE.TOTP })
		expect(wrapper.vm.secret).toBe('JBSWY3DPEHPK3PXP')
		expect(wrapper.vm.qrUrl).toBe('otpauth://totp/x')
		expect(wrapper.findComponent({ name: 'SetupConfirmation' }).exists()).toBe(true)
	})

	it('ends in the enabled state after a correct confirmation code', async () => {
		// Confirm flips the store to ENABLED, mirroring a backend that accepts the code.
		storeRef.current.confirm.mockImplementation(() => {
			storeRef.current.otpState = STATE.ENABLED
		})
		const wrapper = shallowMount(PersonalOtpSettings, mountOptions)
		await wrapper.vm.toggle(true)
		await flushPromises()

		await wrapper.vm.confirm('755224')
		await flushPromises()

		expect(storeRef.current.confirm).toHaveBeenCalledWith('755224')
		expect(wrapper.vm.enabled).toBe(true)
		expect(wrapper.vm.secret).toBeUndefined()
	})

	it('resets the switch and notifies when setup fails', async () => {
		storeRef.current.enable.mockRejectedValueOnce(new Error('boom'))
		const wrapper = shallowMount(PersonalOtpSettings, mountOptions)

		await wrapper.vm.toggle(true)
		await flushPromises()

		expect(wrapper.vm.enabled).toBe(false)
		expect(wrapper.vm.secret).toBeUndefined()
		expect(OC.Notification.showTemporary).toHaveBeenCalled()
	})

	it('shows the managed note and hides the switch for admin-managed tokens', () => {
		loadState.mockReturnValue(true)
		const wrapper = shallowMount(PersonalOtpSettings, mountOptions)

		expect(wrapper.vm.managed).toBe(true)
		expect(wrapper.findComponent({ name: 'NcNoteCard' }).exists()).toBe(true)
		expect(wrapper.findComponent({ name: 'NcCheckboxRadioSwitch' }).exists()).toBe(false)
	})
})
