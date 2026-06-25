/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createApp } from 'vue'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'

import AdminSettings from './components/AdminSettings.vue'

const app = createApp(AdminSettings)
app.mixin({ methods: { t, n } })
app.mount('#twofactor-oath-admin')
