/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { loadState } from '@nextcloud/initial-state'
import { translatePlural as n, translate as t } from '@nextcloud/l10n'
import { createPinia } from 'pinia'
import { createApp } from 'vue'
import PersonalOtpSettings from './components/PersonalOtpSettings.vue'
import logger from './logger.js'
import { useOtpStore } from './store.js'

import '@nextcloud/password-confirmation/style.css'

const pinia = createPinia()
const app = createApp(PersonalOtpSettings)
app.mixin({ methods: { t, n } })
app.use(pinia)

const store = useOtpStore(pinia)
store.otpState = loadState('twofactor_oath', 'state')

app.mount('#twofactor-oath-settings')

logger.debug('personal OTP settings mounted')
