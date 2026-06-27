/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { shallowMount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import AdvancedSettings from './AdvancedSettings.vue'
import { ALGORITHM, DEFAULTS, TYPE } from '../constants.js'

vi.mock('@nextcloud/vue', () => ({
	NcCheckboxRadioSwitch: { name: 'NcCheckboxRadioSwitch', template: '<label><slot /></label>' },
	NcTextField: { name: 'NcTextField', template: '<input>' },
}))

const tMock = (app, text) => text
const globalMocks = { mocks: { t: tMock } }

const mountWith = (settings = {}) => shallowMount(AdvancedSettings, {
	props: { modelValue: { ...DEFAULTS, ...settings } },
	global: globalMocks,
})

beforeEach(() => {
	globalThis.t = tMock
})

describe('AdvancedSettings', () => {
	it('emits update:modelValue when a setting changes', () => {
		const wrapper = mountWith()
		wrapper.vm.algorithm = ALGORITHM.SHA256
		expect(wrapper.emitted('update:modelValue')[0][0]).toMatchObject({ algorithm: ALGORITHM.SHA256 })
	})

	it('composes the OCRA suite from the current settings', () => {
		const wrapper = mountWith({ type: TYPE.OCRA, algorithm: ALGORITHM.SHA256, digits: 8, challengeLength: 10 })
		expect(wrapper.vm.suite).toBe('OCRA-1:HOTP-SHA256-8:QN10')
	})

	it('flags an invalid custom secret', () => {
		const wrapper = mountWith({ secret: 'abc!' })
		expect(wrapper.vm.secretError).not.toBe('')
	})

	it('enforces the strict-RFC minimum of 6 digits for TOTP', () => {
		const wrapper = mountWith({ type: TYPE.TOTP })
		expect(wrapper.vm.digitsOptions[0].value).toBe(6)
	})
})
