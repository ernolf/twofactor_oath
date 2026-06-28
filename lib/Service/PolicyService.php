<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorOath\Service;

use OCA\TwoFactorOath\AppInfo\Application;
use OCA\TwoFactorOath\Constants;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use Override;

/**
 * Decides whether a user's OTP is admin-managed (no self-service):
 *  - if "managed groups" are set, members of those groups are managed;
 *  - else if "excluded groups" are set, everyone EXCEPT their members is managed;
 *  - if neither is set, nobody is managed (everyone may self-service).
 */
final class PolicyService implements IPolicyService {
	public function __construct(
		private readonly IAppConfig $appConfig,
		private readonly IGroupManager $groupManager,
		private readonly IUserManager $userManager,
	) {
	}

	#[Override]
	public function isManaged(IUser $user): bool {
		$managed = $this->getManagedGroups();
		$excluded = $this->getExcludedGroups();
		$userGroups = $this->groupManager->getUserGroupIds($user);

		if (count($managed) > 0) {
			return count(array_intersect($userGroups, $managed)) > 0;
		}
		if (count($excluded) > 0) {
			return count(array_intersect($userGroups, $excluded)) === 0;
		}

		return false;
	}

	/**
	 * All users that are admin-managed under the current policy.
	 *
	 * @return IUser[]
	 */
	#[Override]
	public function listManagedUsers(): array {
		$managed = $this->getManagedGroups();
		$excluded = $this->getExcludedGroups();
		$users = [];

		if (count($managed) > 0) {
			foreach ($managed as $gid) {
				$group = $this->groupManager->get($gid);
				if ($group === null) {
					continue;
				}
				foreach ($group->getUsers() as $user) {
					$users[$user->getUID()] = $user;
				}
			}

			return array_values($users);
		}

		if (count($excluded) > 0) {
			foreach ($this->userManager->search('') as $user) {
				if ($this->isManaged($user)) {
					$users[$user->getUID()] = $user;
				}
			}

			return array_values($users);
		}

		return [];
	}

	/** @return string[] */
	#[Override]
	public function getManagedGroups(): array {
		return $this->appConfig->getValueArray(Application::APP_ID, Constants::CONFIG_MANAGED_GROUPS);
	}

	/** @return string[] */
	#[Override]
	public function getExcludedGroups(): array {
		return $this->appConfig->getValueArray(Application::APP_ID, Constants::CONFIG_EXCLUDED_GROUPS);
	}
}
