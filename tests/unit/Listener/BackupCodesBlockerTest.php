<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorOath\Tests\Unit\Listener;

use OCA\TwoFactorOath\Listener\BackupCodesBlocker;
use OCA\TwoFactorOath\Service\IBackupCodesGuard;
use OCP\Authentication\TwoFactorAuth\IProvider;
use OCP\Authentication\TwoFactorAuth\TwoFactorProviderForUserRegistered;
use OCP\EventDispatcher\Event;
use OCP\IUser;
use OCP\User\Events\PostLoginEvent;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the events that trigger the backup-code rule. Whether the rule
 * applies to a user is the guard's decision, so the guard carries the assertions.
 */
final class BackupCodesBlockerTest extends TestCase {
	private IBackupCodesGuard&MockObject $guard;
	private IUser&MockObject $user;
	private BackupCodesBlocker $listener;

	protected function setUp(): void {
		$this->guard = $this->createMock(IBackupCodesGuard::class);
		$this->user = $this->createMock(IUser::class);
		$this->listener = new BackupCodesBlocker($this->guard);
	}

	public function testRegisteredFactorTriggersTheRule(): void {
		$this->guard->expects($this->once())->method('enforce')->with($this->user);

		$this->listener->handle(new TwoFactorProviderForUserRegistered($this->user, $this->createMock(IProvider::class)));
	}

	public function testPasswordLoginTriggersTheRule(): void {
		$this->guard->expects($this->once())->method('enforce')->with($this->user);

		$this->listener->handle(new PostLoginEvent($this->user, 'alice', 'secret', false));
	}

	public function testTokenLoginIsIgnored(): void {
		$this->guard->expects($this->never())->method('enforce');

		$this->listener->handle(new PostLoginEvent($this->user, 'alice', 'app-password', true));
	}

	public function testUnrelatedEventIsIgnored(): void {
		$this->guard->expects($this->never())->method('enforce');

		$this->listener->handle(new Event());
	}
}
