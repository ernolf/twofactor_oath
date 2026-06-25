<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorOath\Service;

use OCA\TwoFactorOath\Constants;
use OCA\TwoFactorOath\Provider\OtpProvider;
use OCP\Authentication\TwoFactorAuth\IRegistry;
use OCP\IDBConnection;
use OCP\IServerContainer;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Security\ICrypto;
use Throwable;

/**
 * Imports enabled accounts from the bundled twofactor_totp app so admins can
 * switch over without locking users out. Both apps store the secret encrypted
 * with the instance ICrypto, so the secret is portable. twofactor_totp 17.1.0
 * added an 'algorithm' column (string sha1/sha256/sha512, default sha1); digits
 * and period are not per-token there (always 6 and 30). We read 'algorithm' when
 * the column exists and use the OATH defaults for the rest.
 */
final class TotpImporter {
	/** twofactor_totp enrollment state for an active token. */
	private const TOTP_STATE_ENABLED = 2;

	/** twofactor_totp provider id in the two-factor registry. */
	private const TOTP_PROVIDER_ID = 'totp';

	/**
	 * Internal Nextcloud manager that disables a provider for a user (the same path
	 * as `occ twofactorauth:disable`). Resolved lazily by name so the app does not
	 * carry a compile-time dependency on a non-OCP class.
	 */
	private const PROVIDER_MANAGER_CLASS = 'OC\\Authentication\\TwoFactorAuth\\ProviderManager';

	public function __construct(
		private readonly IDBConnection $db,
		private readonly OtpService $otpService,
		private readonly IUserManager $userManager,
		private readonly IRegistry $registry,
		private readonly OtpProvider $provider,
		private readonly ICrypto $crypto,
		private readonly IServerContainer $serverContainer,
	) {
	}

	/**
	 * Number of enabled twofactor_totp accounts that are actually importable, i.e.
	 * whose user does not already have an OATH token. Returns 0 if the table is
	 * absent.
	 */
	public function available(): int {
		try {
			$qb = $this->db->getQueryBuilder();
			$qb->select($qb->func()->count('*', 'cnt'))
				->from('twofactor_totp_secrets', 't')
				->leftJoin('t', 'twofactor_oath_secrets', 'o', $qb->expr()->eq('t.user_id', 'o.user_id'))
				->where($qb->expr()->eq('t.state', $qb->createNamedParameter(self::TOTP_STATE_ENABLED)))
				->andWhere($qb->expr()->isNull('o.user_id'));
			$result = $qb->executeQuery();
			$count = (int)$result->fetchOne();
			$result->closeCursor();

			return $count;
		} catch (Throwable) {
			return 0;
		}
	}

	/**
	 * User ids registered with BOTH apps: they have an OATH token and still an
	 * enabled twofactor_totp secret. These are not importable (they already have
	 * OATH), so an import never cleans them up; their twofactor_totp registration
	 * should be removed so it cannot break login once twofactor_totp is disabled.
	 * Empty if the twofactor_totp table is absent.
	 *
	 * @return string[]
	 */
	public function duplicateUserIds(): array {
		try {
			$qb = $this->db->getQueryBuilder();
			$qb->select('t.user_id')
				->from('twofactor_totp_secrets', 't')
				->innerJoin('t', 'twofactor_oath_secrets', 'o', $qb->expr()->eq('t.user_id', 'o.user_id'))
				->where($qb->expr()->eq('t.state', $qb->createNamedParameter(self::TOTP_STATE_ENABLED)));
			$result = $qb->executeQuery();
			$rows = $result->fetchAll();
			$result->closeCursor();

			return array_map(static fn (array $row): string => (string)$row['user_id'], $rows);
		} catch (Throwable) {
			return [];
		}
	}

	/**
	 * Deregister twofactor_totp for every user that has both an OATH token and an
	 * enabled twofactor_totp secret (see {@see duplicateUserIds()}). Safe: each of
	 * these users keeps their OATH token.
	 *
	 * @return array{cleaned: int}
	 */
	public function cleanUpDuplicates(): array {
		$cleaned = 0;
		foreach ($this->duplicateUserIds() as $uid) {
			$user = $this->userManager->get($uid);
			if ($user === null) {
				continue;
			}
			$this->deregisterTotp($user);
			$cleaned++;
		}

		return ['cleaned' => $cleaned];
	}

	/**
	 * Import all enabled twofactor_totp accounts that do not already have an OATH
	 * token. Existing OATH tokens are never overwritten.
	 *
	 * @return array{imported: int, skipped: int}
	 */
	public function import(): array {
		try {
			$qb = $this->db->getQueryBuilder();
			$qb->select('*')->from('twofactor_totp_secrets');
			$result = $qb->executeQuery();
			$rows = $result->fetchAll();
			$result->closeCursor();
		} catch (Throwable) {
			return ['imported' => 0, 'skipped' => 0];
		}

		$imported = 0;
		$skipped = 0;
		foreach ($rows as $row) {
			if ((int)($row['state'] ?? 0) !== self::TOTP_STATE_ENABLED) {
				continue;
			}
			$uid = (string)($row['user_id'] ?? '');
			$user = $uid !== '' ? $this->userManager->get($uid) : null;
			if ($user === null) {
				$skipped++;
				continue;
			}
			if ($this->otpService->findByUserId($uid) !== null) {
				// Already migrated to OATH: make sure twofactor_totp is no longer a
				// registered second factor for this user, so it cannot linger.
				$this->deregisterTotp($user);
				$skipped++;
				continue;
			}

			try {
				$secret = $this->crypto->decrypt((string)$row['secret']);
			} catch (Throwable) {
				$skipped++;
				continue;
			}

			// twofactor_totp 17.1.0+ stores 'algorithm' (string); digits/period are
			// always 6/30 there, so use the OATH defaults for those.
			$algorithm = isset($row['algorithm']) && $row['algorithm'] !== ''
				? ($this->otpService->algorithmFromName(strtolower((string)$row['algorithm'])) ?? Constants::DEFAULT_ALGORITHM)
				: Constants::DEFAULT_ALGORITHM;

			try {
				$this->otpService->createSecret(
					$uid,
					type: Constants::TYPE_TOTP,
					algorithm: $algorithm,
					digits: Constants::DEFAULT_DIGITS,
					period: Constants::DEFAULT_PERIOD,
					customSecret: $secret,
					state: Constants::STATE_ENABLED,
					locked: false,
					trusted: true,
				);
				$this->registry->enableProviderFor($this->provider, $user);
				// Switch the user off twofactor_totp. Nextcloud tracks the configured
				// second factor in the provider registry, not in the secret, so the
				// secret alone is not enough; this removes the secret and sets the
				// registry state to disabled.
				$this->deregisterTotp($user);
				$imported++;
			} catch (Throwable) {
				$skipped++;
			}
		}

		return ['imported' => $imported, 'skipped' => $skipped];
	}

	/**
	 * Disable the twofactor_totp provider for the user the canonical Nextcloud way
	 * (ProviderManager::tryDisableProviderFor, as used by `occ twofactorauth:disable`).
	 * For twofactor_totp this deletes its secret and sets the registry state to
	 * disabled, so the account no longer counts as a configured second factor and
	 * cannot trigger a "could not load" error once twofactor_totp is disabled.
	 * twofactor_totp must be enabled for its provider to load, which the import
	 * action ensures.
	 */
	private function deregisterTotp(IUser $user): void {
		try {
			$providerManager = $this->serverContainer->get(self::PROVIDER_MANAGER_CLASS);
			/** @psalm-suppress MixedMethodCall */
			$providerManager->tryDisableProviderFor(self::TOTP_PROVIDER_ID, $user);
		} catch (Throwable) {
			// twofactor_totp not loadable or the internal manager is unavailable:
			// leave it untouched rather than guess.
		}
	}
}
