/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

delete webpackConfig.entry.main
webpackConfig.entry.settings = path.join(__dirname, 'src', 'main-settings.js')
webpackConfig.entry.admin = path.join(__dirname, 'src', 'main-admin.js')
webpackConfig.entry['login-setup'] = path.join(__dirname, 'src', 'main-login-setup.js')

module.exports = webpackConfig
