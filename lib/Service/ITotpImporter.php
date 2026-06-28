<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorOath\Service;

/**
 * twofactor_totp migration helper the admin controller depends on. Lets it be
 * unit-tested with a mock instead of the concrete TotpImporter.
 */
interface ITotpImporter {
	/** Number of enabled twofactor_totp accounts that are importable (0 if absent). */
	public function available(): int;

	/**
	 * User ids registered with both twofactor_totp and OATH.
	 *
	 * @return string[]
	 */
	public function duplicateUserIds(): array;

	/**
	 * Deregister twofactor_totp for every user that has both apps.
	 *
	 * @return array{cleaned: int}
	 */
	public function cleanUpDuplicates(): array;

	/**
	 * Import all enabled twofactor_totp accounts that have no OATH token yet.
	 *
	 * @return array{imported: int, skipped: int}
	 */
	public function import(): array;
}
