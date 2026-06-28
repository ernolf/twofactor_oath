<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorOath\Service;

use OCP\IUser;

/**
 * Managed/excluded-group policy the controllers and provider depend on. Lets them
 * be unit-tested with a mock instead of the concrete PolicyService.
 */
interface IPolicyService {
	/** Whether the user's OTP is admin-managed (no self-service). */
	public function isManaged(IUser $user): bool;

	/**
	 * All users that are admin-managed under the current policy.
	 *
	 * @return IUser[]
	 */
	public function listManagedUsers(): array;

	/** @return string[] */
	public function getManagedGroups(): array;

	/** @return string[] */
	public function getExcludedGroups(): array;
}
