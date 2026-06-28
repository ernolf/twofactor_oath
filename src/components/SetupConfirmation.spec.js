/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { shallowMount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import SetupConfirmation from './SetupConfirmation.vue'

vi.mock('@nextcloud/router', () => ({ imagePath: () => '/img/app-dark.svg' }))
vi.mock('@chenfengyuan/vue-qrcode', () => ({ default: { name: 'Qrcode', render: () => null } }))
vi.mock('@nextcloud/vue', () => ({
	NcButton: { name: 'NcButton', template: '<button><slot /></button>' },
	NcTextField: { name: 'NcTextField', template: '<input>' },
}))

const tMock = (app, text) => text
const baseProps = { secret: 'JBSWY3DPEHPK3PXP', qrUrl: 'otpauth://totp/x?image=https://example.com/i.png' }
const mountOptions = { props: baseProps, global: { mocks: { t: tMock } } }

beforeEach(() => {
	globalThis.t = tMock
	globalThis.OC = { Notification: { showTemporary: vi.fn() } }
})

describe('SetupConfirmation', () => {
	it('emits confirm with the entered code', () => {
		const wrapper = shallowMount(SetupConfirmation, mountOptions)
		wrapper.vm.code = '755224'
		wrapper.vm.confirm()
		expect(wrapper.emitted('confirm')).toEqual([['755224']])
	})

	it('does not emit confirm for an empty code', () => {
		const wrapper = shallowMount(SetupConfirmation, mountOptions)
		wrapper.vm.code = ''
		wrapper.vm.confirm()
		expect(wrapper.emitted('confirm')).toBeUndefined()
	})

	it('clears the entered code when a fresh QR is generated', async () => {
		const wrapper = shallowMount(SetupConfirmation, mountOptions)
		wrapper.vm.code = '123'
		await wrapper.setProps({ qrUrl: 'otpauth://totp/y' })
		expect(wrapper.vm.code).toBe('')
	})

	it('renders the challenge instead of the QR in OCRA mode', () => {
		const wrapper = shallowMount(SetupConfirmation, { props: { ...baseProps, ocra: true, challenge: '12345678' }, global: mountOptions.global })
		expect(wrapper.findComponent({ name: 'Qrcode' }).exists()).toBe(false)
		expect(wrapper.text()).toContain('12345678')
	})
})
