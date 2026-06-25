<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorOath\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<OtpSecret>
 */
final class OtpSecretMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'twofactor_oath_secrets', OtpSecret::class);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function getByUserId(string $userId): OtpSecret {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));

		return $this->findEntity($qb);
	}

	public function hasSecret(string $userId): bool {
		try {
			$this->getByUserId($userId);
			return true;
		} catch (DoesNotExistException) {
			return false;
		}
	}

	public function deleteByUserId(string $userId): void {
		try {
			$this->delete($this->getByUserId($userId));
		} catch (DoesNotExistException) {
			// nothing to delete
		}
	}
}
