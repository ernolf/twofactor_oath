/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { loadState } from '@nextcloud/initial-state'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'

import '@nextcloud/password-confirmation/style.css'

import logger from './logger.js'
import { useOtpStore } from './store.js'
import PersonalOtpSettings from './components/PersonalOtpSettings.vue'

const pinia = createPinia()
const app = createApp(PersonalOtpSettings)
app.mixin({ methods: { t, n } })
app.use(pinia)

const store = useOtpStore(pinia)
store.otpState = loadState('twofactor_oath', 'state')

app.mount('#twofactor-oath-settings')

logger.debug('personal OTP settings mounted')
