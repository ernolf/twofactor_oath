/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { translatePlural as n, translate as t } from '@nextcloud/l10n'
import { createApp } from 'vue'
import LoginSetup from './components/LoginSetup.vue'

const app = createApp(LoginSetup)
app.mixin({ methods: { t, n } })
app.mount('#twofactor-oath-login-setup')
