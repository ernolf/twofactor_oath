<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorOath\Tests\Unit\Service;

use OCA\TwoFactorOath\Service\IOtpService;
use OCA\TwoFactorOath\Service\TotpImporter;
use OCP\Authentication\TwoFactorAuth\IProvider;
use OCP\Authentication\TwoFactorAuth\IRegistry;
use OCP\IDBConnection;
use OCP\IServerContainer;
use OCP\IUserManager;
use OCP\Security\ICrypto;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for the resilience contract: when the twofactor_totp table is
 * absent the query builder call fails, and every public method must degrade
 * gracefully instead of throwing. Importing real rows is covered by integration
 * tests against a database that has the twofactor_totp schema.
 */
final class TotpImporterTest extends TestCase {
	private IDBConnection&MockObject $db;
	private TotpImporter $importer;

	protected function setUp(): void {
		$this->db = $this->createMock(IDBConnection::class);
		$this->db->method('getQueryBuilder')->willThrowException(new RuntimeException('no such table'));

		$this->importer = new TotpImporter(
			$this->db,
			$this->createMock(IOtpService::class),
			$this->createMock(IUserManager::class),
			$this->createMock(IRegistry::class),
			$this->createMock(IProvider::class),
			$this->createMock(ICrypto::class),
			$this->createMock(IServerContainer::class),
		);
	}

	public function testAvailableReturnsZeroWhenTableMissing(): void {
		$this->assertSame(0, $this->importer->available());
	}

	public function testDuplicateUserIdsReturnsEmptyWhenTableMissing(): void {
		$this->assertSame([], $this->importer->duplicateUserIds());
	}

	public function testImportReturnsZeroesWhenTableMissing(): void {
		$this->assertSame(['imported' => 0, 'skipped' => 0], $this->importer->import());
	}

	public function testCleanUpDuplicatesReturnsZeroWhenNoDuplicates(): void {
		$this->assertSame(['cleaned' => 0], $this->importer->cleanUpDuplicates());
	}
}
