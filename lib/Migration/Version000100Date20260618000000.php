<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorOath\Migration;

use Closure;
use OCA\TwoFactorOath\Constants;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

/**
 * Initial schema. Carries TOTP + HOTP and the admin-lock flag from day one,
 * so the planned features (HOTP, admin-managed entries, bulk provisioning)
 * need no further schema migration.
 */
final class Version000100Date20260618000000 extends SimpleMigrationStep {

	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('twofactor_oath_secrets')) {
			return null;
		}

		$table = $schema->createTable('twofactor_oath_secrets');
		$table->addColumn('id', Types::BIGINT, [
			'autoincrement' => true,
			'notnull' => true,
			'length' => 20,
		]);
		$table->addColumn('user_id', Types::STRING, [
			'notnull' => true,
			'length' => 64,
		]);
		// OTP type as integer code (1=TOTP, 2=HOTP).
		$table->addColumn('type', Types::SMALLINT, [
			'notnull' => true,
			'default' => Constants::DEFAULT_TYPE,
		]);
		// Base32 secret
		$table->addColumn('secret', Types::TEXT, [
			'notnull' => true,
		]);
		// Hash algorithm as integer code (1=SHA1, 2=SHA256, 3=SHA512).
		$table->addColumn('algorithm', Types::SMALLINT, [
			'notnull' => true,
			'default' => Constants::DEFAULT_ALGORITHM,
		]);
		// OTP token length
		$table->addColumn('digits', Types::SMALLINT, [
			'notnull' => true,
			'default' => Constants::DEFAULT_DIGITS,
		]);
		// TOTP step in seconds
		$table->addColumn('period', Types::SMALLINT, [
			'notnull' => true,
			'default' => Constants::DEFAULT_PERIOD,
		]);
		// HOTP counter
		$table->addColumn('counter', Types::BIGINT, [
			'notnull' => true,
			'default' => Constants::DEFAULT_COUNTER,
			'length' => 20,
		]);
		// TOTP epoch / T0 (RFC 6238), in seconds; 0 = Unix epoch.
		$table->addColumn('epoch', Types::BIGINT, [
			'notnull' => true,
			'default' => Constants::DEFAULT_EPOCH,
			'length' => 20,
		]);
		// OCRA suite string (RFC 6287), only for type=OCRA; null otherwise.
		$table->addColumn('suite', Types::STRING, [
			'notnull' => false,
			'length' => 64,
		]);
		// Enrollment state (1 = created, 2 = enabled).
		$table->addColumn('state', Types::SMALLINT, [
			'notnull' => true,
			'default' => Constants::STATE_CREATED,
		]);
		// admin-managed: user cannot view, change or disable.
		// Boolean columns must be nullable: Nextcloud rejects NOT NULL bool
		// columns (a bool is an integer of length 1 and could not store false).
		$table->addColumn('locked', Types::BOOLEAN, [
			'notnull' => false,
			'default' => false,
		]);
		// anti-replay: last accepted time slice (TOTP) or counter (HOTP)
		$table->addColumn('last_used', Types::BIGINT, [
			'notnull' => false,
			'length' => 20,
		]);
		$table->addColumn('created_at', Types::BIGINT, [
			'notnull' => true,
			'default' => 0,
			'length' => 20,
		]);

		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['user_id'], 'twofactor_oath_uid_uniq');

		return $schema;
	}
}
