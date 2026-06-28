/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import SelectField from './SelectField.vue'

// staticWidth is on in tests: the width measurement uses a canvas, which jsdom
// does not implement.
const options = [
	{ value: 30, label: '30s' },
	{ value: 60, label: '1m' },
]
const mountOptions = { props: { options, modelValue: 30, staticWidth: true } }

describe('SelectField', () => {
	it('renders one option per entry', () => {
		const wrapper = mount(SelectField, mountOptions)
		expect(wrapper.findAll('option')).toHaveLength(2)
	})

	it('emits the selected value with its original type preserved', async () => {
		const wrapper = mount(SelectField, mountOptions)

		await wrapper.find('select').setValue('60')

		expect(wrapper.emitted('update:modelValue')).toEqual([[60]])
	})

	it('accepts plain primitive options', () => {
		const wrapper = mount(SelectField, { props: { options: ['a', 'b', 'c'], modelValue: 'a', staticWidth: true } })
		expect(wrapper.findAll('option')).toHaveLength(3)
	})
})
