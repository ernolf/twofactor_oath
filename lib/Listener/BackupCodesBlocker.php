<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorOath\Listener;

use OCA\TwoFactorOath\Service\IBackupCodesGuard;
use OCP\Authentication\TwoFactorAuth\TwoFactorProviderForUserRegistered;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\PostLoginEvent;
use Override;

/**
 * Enforces the backup-code rule at the two points where it can be broken:
 *  - a second factor is registered for a user: either the backup codes just
 *    generated, or another factor, after which twofactor_backupcodes schedules
 *    its reminder to generate codes;
 *  - a password login, which dispatches PostLoginEvent before the second-factor
 *    challenge, so codes that predate the rule or a group change are revoked
 *    before they can be entered. Token logins skip the challenge and are ignored.
 *
 * @template-implements IEventListener<TwoFactorProviderForUserRegistered|PostLoginEvent>
 */
final class BackupCodesBlocker implements IEventListener {
	/**
	 * Below the default priority, so this runs after twofactor_backupcodes has
	 * handled the same registration event and scheduled its reminder.
	 */
	public const PRIORITY = -100;

	public function __construct(
		private readonly IBackupCodesGuard $guard,
	) {
	}

	#[Override]
	public function handle(Event $event): void {
		if ($event instanceof TwoFactorProviderForUserRegistered) {
			$this->guard->enforce($event->getUser());
			return;
		}

		if ($event instanceof PostLoginEvent && !$event->isTokenLogin()) {
			$this->guard->enforce($event->getUser());
		}
	}
}
