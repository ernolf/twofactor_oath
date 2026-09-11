<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorOath\Listener;

use OCA\TwoFactorOath\Db\IOtpSecretMapper;
use OCP\DB\Exception;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserDeletedEvent;
use Override;
use Psr\Log\LoggerInterface;

/**
 * Removes a deleted user's OATH secret. That table is the only per-user state this
 * app keeps; the core two-factor registry entry is cleaned up by the server itself.
 *
 * @template-implements IEventListener<UserDeletedEvent>
 */
final class UserDeleted implements IEventListener {
	public function __construct(
		private readonly IOtpSecretMapper $mapper,
		private readonly LoggerInterface $logger,
	) {
	}

	#[Override]
	public function handle(Event $event): void {
		if (!$event instanceof UserDeletedEvent) {
			return;
		}

		$userId = $event->getUser()->getUID();

		try {
			$this->mapper->deleteByUserId($userId);
		} catch (Exception $e) {
			// A user deletion must not fail over a leftover row; report and move on.
			$this->logger->error('Failed to delete the OATH secret of a deleted user', [
				'userId' => $userId,
				'exception' => $e,
			]);
		}
	}
}
