<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

return [
	'routes' => [
		['name' => 'settings#state', 'url' => '/settings/state', 'verb' => 'GET'],
		['name' => 'settings#enable', 'url' => '/settings/enable', 'verb' => 'POST'],
		['name' => 'settings#resync', 'url' => '/settings/resync', 'verb' => 'POST'],
		['name' => 'settings#config', 'url' => '/settings/config', 'verb' => 'GET'],
		['name' => 'settings#show', 'url' => '/settings/show', 'verb' => 'POST'],
		['name' => 'settings#deactivate', 'url' => '/settings/deactivate', 'verb' => 'POST'],
		['name' => 'admin#setSecretLength', 'url' => '/admin/secret-length', 'verb' => 'POST'],
		['name' => 'admin#setManagedGroups', 'url' => '/admin/managed-groups', 'verb' => 'POST'],
		['name' => 'admin#setExcludedGroups', 'url' => '/admin/excluded-groups', 'verb' => 'POST'],
		['name' => 'admin#exportUsers', 'url' => '/admin/export-users', 'verb' => 'GET'],
		['name' => 'admin#managedUsers', 'url' => '/admin/managed-users', 'verb' => 'GET'],
		['name' => 'admin#showToken', 'url' => '/admin/show-token', 'verb' => 'GET'],
		['name' => 'admin#provision', 'url' => '/admin/provision', 'verb' => 'POST'],
		['name' => 'admin#disableUsers', 'url' => '/admin/disable-users', 'verb' => 'POST'],
		['name' => 'admin#importFromTotp', 'url' => '/admin/import-totp', 'verb' => 'POST'],
		['name' => 'admin#cleanupTotpDuplicates', 'url' => '/admin/cleanup-totp-duplicates', 'verb' => 'POST'],
		['name' => 'admin#totpStatus', 'url' => '/admin/totp-status', 'verb' => 'GET'],
	],
];
