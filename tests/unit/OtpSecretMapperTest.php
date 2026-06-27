<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorOath\Tests\Unit;

use OCA\TwoFactorOath\Constants;
use OCA\TwoFactorOath\Db\OtpSecret;
use OCA\TwoFactorOath\Db\OtpSecretMapper;
use OCP\App\IAppManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\Exception as DbException;
use OCP\Server;
use Test\TestCase;

/**
 * Integration tests for OtpSecretMapper against a real database. They exercise
 * the schema created by the app's migration on every supported backend
 * (sqlite, mysql, pgsql, oci). The focus is on the values that behave
 * differently per database: the nullable boolean column (locked) and the
 * nullable bigint/string columns (last_used, suite), plus the unique index on
 * user_id.
 *
 * @group DB
 */
class OtpSecretMapperTest extends TestCase {
	private const TEST_USER = 'test-oath-user';
	private const OTHER_USER = 'test-oath-user-2';

	/** Base32 of the RFC 4226/6287 test key "12345678901234567890" (20 bytes). */
	private const SECRET_B32 = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

	private OtpSecretMapper $mapper;

	protected function setUp(): void {
		parent::setUp();

		// Enabling the app runs its migration, so the table exists before the test.
		Server::get(IAppManager::class)->enableApp('twofactor_oath');
		$this->mapper = Server::get(OtpSecretMapper::class);

		$this->cleanup();
	}

	protected function tearDown(): void {
		$this->cleanup();
		parent::tearDown();
	}

	private function cleanup(): void {
		$this->mapper->deleteByUserId(self::TEST_USER);
		$this->mapper->deleteByUserId(self::OTHER_USER);
	}

	private function newSecret(string $userId): OtpSecret {
		$entity = new OtpSecret();
		$entity->setUserId($userId);
		$entity->setType(Constants::TYPE_TOTP);
		$entity->setSecret(self::SECRET_B32);
		$entity->setAlgorithm(Constants::ALGO_SHA1);
		$entity->setDigits(6);
		$entity->setPeriod(30);
		$entity->setCounter(0);
		$entity->setEpoch(0);
		$entity->setState(Constants::STATE_CREATED);
		$entity->setLocked(false);
		$entity->setLastUsed(null);
		$entity->setSuite(null);
		$entity->setCreatedAt(1700000000);

		return $entity;
	}

	public function testInsertAndGetRoundTrip(): void {
		$inserted = $this->mapper->insert($this->newSecret(self::TEST_USER));
		$this->assertNotNull($inserted->getId());

		$loaded = $this->mapper->getByUserId(self::TEST_USER);
		$this->assertSame(self::TEST_USER, $loaded->getUserId());
		$this->assertSame(Constants::TYPE_TOTP, $loaded->getType());
		$this->assertSame(self::SECRET_B32, $loaded->getSecret());
		$this->assertSame(Constants::ALGO_SHA1, $loaded->getAlgorithm());
		$this->assertSame(6, $loaded->getDigits());
		$this->assertSame(30, $loaded->getPeriod());
		$this->assertSame(0, $loaded->getCounter());
		$this->assertSame(0, $loaded->getEpoch());
		$this->assertSame(Constants::STATE_CREATED, $loaded->getState());
		$this->assertSame(1700000000, $loaded->getCreatedAt());
		// Nullable columns: a stored false / null must round-trip unchanged
		// across all backends (the Oracle/pgsql boolean handling differs).
		$this->assertFalse($loaded->getLocked());
		$this->assertNull($loaded->getLastUsed());
		$this->assertNull($loaded->getSuite());
	}

	public function testHasSecret(): void {
		$this->assertFalse($this->mapper->hasSecret(self::TEST_USER));

		$this->mapper->insert($this->newSecret(self::TEST_USER));

		$this->assertTrue($this->mapper->hasSecret(self::TEST_USER));
	}

	public function testUpdatePersistsChanges(): void {
		$entity = $this->mapper->insert($this->newSecret(self::TEST_USER));

		$entity->setCounter(42);
		$entity->setState(Constants::STATE_ENABLED);
		$entity->setLocked(true);
		$entity->setLastUsed(99);
		$entity->setSuite('OCRA-1:HOTP-SHA1-6:QN08');
		$this->mapper->update($entity);

		$loaded = $this->mapper->getByUserId(self::TEST_USER);
		$this->assertSame(42, $loaded->getCounter());
		$this->assertSame(Constants::STATE_ENABLED, $loaded->getState());
		$this->assertTrue($loaded->getLocked());
		$this->assertSame(99, $loaded->getLastUsed());
		$this->assertSame('OCRA-1:HOTP-SHA1-6:QN08', $loaded->getSuite());
	}

	public function testDeleteByUserId(): void {
		$this->mapper->insert($this->newSecret(self::TEST_USER));

		$this->mapper->deleteByUserId(self::TEST_USER);

		$this->assertFalse($this->mapper->hasSecret(self::TEST_USER));
		$this->expectException(DoesNotExistException::class);
		$this->mapper->getByUserId(self::TEST_USER);
	}

	public function testDeleteByUserIdIsNoOpWhenMissing(): void {
		// Must not throw when there is nothing to delete.
		$this->mapper->deleteByUserId(self::TEST_USER);
		$this->assertFalse($this->mapper->hasSecret(self::TEST_USER));
	}

	public function testUserIdUniqueConstraint(): void {
		$this->mapper->insert($this->newSecret(self::TEST_USER));

		// The migration declares a unique index on user_id; a second row for the
		// same user must be rejected by the database.
		$this->expectException(DbException::class);
		$this->mapper->insert($this->newSecret(self::TEST_USER));
	}

	public function testSeparateUsersCoexist(): void {
		$this->mapper->insert($this->newSecret(self::TEST_USER));
		$this->mapper->insert($this->newSecret(self::OTHER_USER));

		$this->assertTrue($this->mapper->hasSecret(self::TEST_USER));
		$this->assertTrue($this->mapper->hasSecret(self::OTHER_USER));
	}
}
