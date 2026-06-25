<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use Nextcloud\Rector\Set\NextcloudSets;
use Rector\Config\RectorConfig;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\SafeDeclareStrictTypesRector;

return RectorConfig::configure()
	->withPaths([
		__DIR__ . '/lib',
		__DIR__ . '/tests',
	])
	->withSkip([
		__DIR__ . '/tests/stub.php',
	])
	->withImportNames(
		importShortClasses: false,
	)
	->withPhpSets(
		php81: true,
	)
	->withSets([
		NextcloudSets::NEXTCLOUD_30,
		PHPUnitSetList::PHPUNIT_100,
	])
	->withRules([
		SafeDeclareStrictTypesRector::class,
	]);
