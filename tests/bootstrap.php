<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

define('PHPUNIT_RUN', 1);

$ncBase = __DIR__ . '/../../../lib/base.php';
if (file_exists($ncBase)) {
	// Running inside a Nextcloud server: boot it so tests may use OCP/OC.
	require_once $ncBase;

	// The app's vendored dependencies (otphp, …) are normally loaded by
	// Application::register(), which only runs when the app is enabled. In a
	// CI test instance the app is not enabled, so load them explicitly here.
	require_once __DIR__ . '/../vendor/autoload.php';

	\OC::$composerAutoloader->addPsr4('Test\\', \OC::$SERVERROOT . '/tests/lib/', true);
	\OC::$composerAutoloader->addPsr4('Tests\\', \OC::$SERVERROOT . '/tests/', true);

	\OC_App::loadApp('twofactor_oath');

	\OC_Hook::clear();
} else {
	// Standalone checkout (no server alongside): the app's own autoloader is
	// enough for pure unit tests that do not depend on the Nextcloud server.
	require_once __DIR__ . '/../vendor/autoload.php';
}
