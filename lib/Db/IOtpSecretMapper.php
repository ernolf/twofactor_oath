<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorOath\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;

/**
 * Persistence boundary for OATH secrets. Lets OtpService depend on this abstraction
 * (and be unit-tested with a mock) instead of the concrete QBMapper.
 */
interface IOtpSecretMapper {
	/**
	 * @throws DoesNotExistException
	 */
	public function getByUserId(string $userId): OtpSecret;

	public function deleteByUserId(string $userId): void;

	/**
	 * @param OtpSecret $entity
	 * @return OtpSecret
	 */
	public function insert(Entity $entity): Entity;

	/**
	 * @param OtpSecret $entity
	 * @return OtpSecret
	 */
	public function update(Entity $entity): Entity;
}
