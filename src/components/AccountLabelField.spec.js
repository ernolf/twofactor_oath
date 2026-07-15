/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { shallowMount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import AccountLabelField from './AccountLabelField.vue'

vi.mock('@nextcloud/vue', () => ({
	NcButton: { name: 'NcButton', template: '<button><slot name="icon" /><slot /></button>' },
	NcNoteCard: { name: 'NcNoteCard', template: '<div><slot /></div>' },
	NcTextField: { name: 'NcTextField', template: '<input>' },
}))

const tMock = (app, text) => text
const uri = 'otpauth://totp/My%20Cloud%3Aalice%40cloud.example?secret=A&issuer=My%20Cloud'
const mountOptions = { props: { uri }, global: { mocks: { t: tMock } } }

beforeEach(() => {
	globalThis.t = tMock
})

describe('AccountLabelField', () => {
	it('shows the default local part and the host suffix', () => {
		const w = shallowMount(AccountLabelField, mountOptions)
		expect(w.vm.effectiveLocal).toBe('alice')
		expect(w.vm.suffix).toBe('@cloud.example')
	})

	it('does not emit on mount', () => {
		const w = shallowMount(AccountLabelField, mountOptions)
		expect(w.emitted('update')).toBeUndefined()
	})

	it('emits the patched URI after editing and confirming, and keeps the value', async () => {
		const w = shallowMount(AccountLabelField, mountOptions)
		w.vm.startEdit()
		w.vm.draft = 'jan'
		w.vm.confirm()
		await w.vm.$nextTick()
		expect(w.emitted('update').at(-1)[0]).toBe('otpauth://totp/My%20Cloud%3Ajan%40cloud.example?secret=A&issuer=My%20Cloud')
		expect(w.vm.effectiveLocal).toBe('jan')
		expect(w.vm.editing).toBe(false)
	})

	it('treats a confirmed default or empty value as "no custom label"', () => {
		const w = shallowMount(AccountLabelField, mountOptions)
		w.vm.startEdit()
		w.vm.draft = 'alice'
		w.vm.confirm()
		expect(w.vm.custom).toBe(null)
	})

	it('strips colons on confirm, so the shown label matches the QR content', () => {
		const w = shallowMount(AccountLabelField, mountOptions)
		w.vm.startEdit()
		w.vm.draft = 'a:b'
		w.vm.confirm()
		expect(w.vm.custom).toBe('ab')
		w.vm.startEdit()
		w.vm.draft = '::'
		w.vm.confirm()
		expect(w.vm.custom).toBe(null)
	})

	it('shows the reset button only while editing a non-default value', () => {
		const w = shallowMount(AccountLabelField, mountOptions)
		expect(w.vm.showReset).toBe(false)
		w.vm.startEdit()
		w.vm.draft = 'alice'
		expect(w.vm.showReset).toBe(false)
		w.vm.draft = 'jan'
		expect(w.vm.showReset).toBe(true)
	})

	it('reset returns to the default and emits the pristine default', async () => {
		const w = shallowMount(AccountLabelField, mountOptions)
		w.vm.startEdit()
		w.vm.draft = 'jan'
		w.vm.confirm()
		await w.vm.$nextTick()
		w.vm.startEdit()
		w.vm.reset()
		await w.vm.$nextTick()
		expect(w.vm.custom).toBe(null)
		expect(w.emitted('update').at(-1)[0]).toBe('')
	})

	it('keeps the custom label when the server URI changes (recreate)', async () => {
		const w = shallowMount(AccountLabelField, mountOptions)
		w.vm.startEdit()
		w.vm.draft = 'jan'
		w.vm.confirm()
		await w.setProps({ uri: 'otpauth://totp/My%20Cloud%3Abob%40cloud.example?secret=B&issuer=My%20Cloud' })
		await w.vm.$nextTick()
		expect(w.vm.custom).toBe('jan')
		const last = w.emitted('update').at(-1)[0]
		expect(last).toContain('jan')
		expect(last).toContain('secret=B')
	})

	it('toggles the help box', async () => {
		const w = shallowMount(AccountLabelField, mountOptions)
		expect(w.findComponent({ name: 'NcNoteCard' }).exists()).toBe(false)
		w.vm.showHelp = true
		await w.vm.$nextTick()
		expect(w.findComponent({ name: 'NcNoteCard' }).exists()).toBe(true)
	})

	it('in managed mode shows the help as a row tooltip, never as a box', async () => {
		const w = shallowMount(AccountLabelField, { props: { uri, managed: true }, global: { mocks: { t: tMock } } })
		expect(w.find('.otp-label__row').attributes('title')).toContain('identify the account')
		w.vm.showHelp = true
		await w.vm.$nextTick()
		expect(w.findComponent({ name: 'NcNoteCard' }).exists()).toBe(false)
	})
})
