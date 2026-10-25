<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorOath\Service;

use OCP\IUser;

/**
 * Keeps managed users off Nextcloud backup codes when the administrator forbids
 * them. Lets the listener and the admin controller be unit-tested with a mock.
 */
interface IBackupCodesGuard {
	/** Whether the administrator forbids backup codes for managed users. */
	public function isEnabled(): bool;

	/** Whether the rule is on and the user is managed, so backup codes are forbidden to them. */
	public function appliesTo(IUser $user): bool;

	/** Revoke the user's backup codes and their reminder, if the rule applies to them. */
	public function enforce(IUser $user): void;

	/** Apply enforce() to every managed user, after the rule or the groups changed. */
	public function enforceAll(): void;
}
