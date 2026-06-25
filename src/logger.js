/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getCurrentUser } from '@nextcloud/auth'
import { getLoggerBuilder } from '@nextcloud/logger'

const builder = getLoggerBuilder().setApp('twofactor_oath')

const user = getCurrentUser()
if (user !== null) {
	builder.setUid(user.uid)
}

export default builder.build()
