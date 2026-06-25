/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createApp } from 'vue'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'

import LoginSetup from './components/LoginSetup.vue'

const app = createApp(LoginSetup)
app.mixin({ methods: { t, n } })
app.mount('#twofactor-oath-login-setup')
