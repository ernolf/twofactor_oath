<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorOath\Tests\Unit\Listener;

use OCA\TwoFactorOath\Db\IOtpSecretMapper;
use OCA\TwoFactorOath\Listener\UserDeleted;
use OCP\DB\Exception;
use OCP\EventDispatcher\Event;
use OCP\IUser;
use OCP\User\Events\UserDeletedEvent;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for the user-deletion cleanup. The listener has one job — hand the
 * deleted user's id to the mapper — so the mapper carries the assertions.
 */
final class UserDeletedTest extends TestCase {
	private IOtpSecretMapper&MockObject $mapper;
	private LoggerInterface&MockObject $logger;
	private UserDeleted $listener;

	protected function setUp(): void {
		$this->mapper = $this->createMock(IOtpSecretMapper::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->listener = new UserDeleted($this->mapper, $this->logger);
	}

	private function deletionOf(string $userId): UserDeletedEvent {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);

		return new UserDeletedEvent($user);
	}

	public function testSecretOfTheDeletedUserIsRemoved(): void {
		$this->mapper->expects($this->once())->method('deleteByUserId')->with('alice');
		$this->logger->expects($this->never())->method('error');

		$this->listener->handle($this->deletionOf('alice'));
	}

	public function testUnrelatedEventIsIgnored(): void {
		$this->mapper->expects($this->never())->method('deleteByUserId');

		$this->listener->handle(new Event());
	}

	public function testDatabaseFailureIsLoggedAndSwallowed(): void {
		$this->mapper->method('deleteByUserId')->willThrowException(new Exception('connection lost'));
		$this->logger->expects($this->once())->method('error');

		$this->listener->handle($this->deletionOf('alice'));
	}
}
