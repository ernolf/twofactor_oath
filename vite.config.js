/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { join, resolve } from 'path'

import { createAppConfig } from '@nextcloud/vite-config'

export default createAppConfig(
	{
		settings: resolve(join('src', 'main-settings.js')),
		admin: resolve(join('src', 'main-admin.js')),
		'login-setup': resolve(join('src', 'main-login-setup.js')),
	}, {
		createEmptyCSSEntryPoints: true,
	},
)
