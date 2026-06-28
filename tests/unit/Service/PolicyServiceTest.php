<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorOath\Tests\Unit\Service;

use OCA\TwoFactorOath\Constants;
use OCA\TwoFactorOath\Service\PolicyService;
use OCP\IAppConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the managed/excluded-group policy. The three policy modes are
 * driven entirely by the two app-config arrays, so the collaborators are mocked.
 */
final class PolicyServiceTest extends TestCase {
	private IAppConfig&MockObject $appConfig;
	private IGroupManager&MockObject $groupManager;
	private IUserManager&MockObject $userManager;
	private PolicyService $service;

	protected function setUp(): void {
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->service = new PolicyService($this->appConfig, $this->groupManager, $this->userManager);
	}

	private function policy(array $managed, array $excluded): void {
		$this->appConfig->method('getValueArray')->willReturnCallback(
			static fn (string $app, string $key): array => match ($key) {
				Constants::CONFIG_MANAGED_GROUPS => $managed,
				Constants::CONFIG_EXCLUDED_GROUPS => $excluded,
				default => [],
			},
		);
	}

	private function userInGroups(array $gids): IUser&MockObject {
		$user = $this->createMock(IUser::class);
		$this->groupManager->method('getUserGroupIds')->with($user)->willReturn($gids);

		return $user;
	}

	public function testNobodyIsManagedWithoutGroups(): void {
		$this->policy([], []);
		$this->assertFalse($this->service->isManaged($this->userInGroups(['users'])));
	}

	public function testMemberOfManagedGroupIsManaged(): void {
		$this->policy(['admins'], []);
		$this->assertTrue($this->service->isManaged($this->userInGroups(['users', 'admins'])));
	}

	public function testNonMemberOfManagedGroupIsNotManaged(): void {
		$this->policy(['admins'], []);
		$this->assertFalse($this->service->isManaged($this->userInGroups(['users'])));
	}

	public function testExcludedGroupManagesEveryoneElse(): void {
		$this->policy([], ['service-accounts']);
		$this->assertTrue($this->service->isManaged($this->userInGroups(['users'])));
	}

	public function testMemberOfExcludedGroupIsNotManaged(): void {
		$this->policy([], ['service-accounts']);
		$this->assertFalse($this->service->isManaged($this->userInGroups(['service-accounts'])));
	}

	public function testListManagedUsersCollectsManagedGroupMembers(): void {
		$this->policy(['admins'], []);
		$alice = $this->createMock(IUser::class);
		$alice->method('getUID')->willReturn('alice');
		$group = $this->createMock(IGroup::class);
		$group->method('getUsers')->willReturn([$alice]);
		$this->groupManager->method('get')->with('admins')->willReturn($group);

		$users = $this->service->listManagedUsers();

		$this->assertSame(['alice'], array_map(static fn (IUser $u): string => $u->getUID(), $users));
	}
}
