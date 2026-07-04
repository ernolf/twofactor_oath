<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorOath\Controller;

use InvalidArgumentException;
use OCA\TwoFactorOath\AppInfo\Application;
use OCA\TwoFactorOath\Constants;
use OCA\TwoFactorOath\Db\IOtpSecretMapper;
use OCA\TwoFactorOath\Service\IOtpService;
use OCA\TwoFactorOath\Service\IPolicyService;
use OCA\TwoFactorOath\Service\ITotpImporter;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Authentication\TwoFactorAuth\IProvider;
use OCP\Authentication\TwoFactorAuth\IRegistry;
use OCP\Defaults;
use OCP\IAppConfig;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;

/**
 * Admin-only endpoints (no #[NoAdminRequired]: a plain Controller method
 * requires an administrator by default).
 */
class AdminController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly IAppConfig $appConfig,
		private readonly IPolicyService $policyService,
		private readonly IOtpService $otpService,
		private readonly IOtpSecretMapper $mapper,
		private readonly IUserManager $userManager,
		private readonly IRegistry $registry,
		private readonly IProvider $provider,
		private readonly Defaults $defaults,
		private readonly ITotpImporter $totpImporter,
		private readonly IAppManager $appManager,
	) {
		parent::__construct($appName, $request);
	}

	private const TOTP_APP_ID = 'twofactor_totp';

	/**
	 * Import enabled accounts from the bundled twofactor_totp app. Only available
	 * while twofactor_totp is enabled.
	 */
	public function importFromTotp(): JSONResponse {
		if (!$this->appManager->isEnabledForAnyone(self::TOTP_APP_ID)) {
			return new JSONResponse(['message' => 'twofactor_totp is not enabled'], Http::STATUS_BAD_REQUEST);
		}

		return new JSONResponse($this->totpImporter->import());
	}

	/**
	 * Deregister twofactor_totp for users registered with both apps (they have an
	 * OATH token and still a twofactor_totp account). Only available while
	 * twofactor_totp is enabled, since its provider must load to be disabled.
	 */
	public function cleanupTotpDuplicates(): JSONResponse {
		if (!$this->appManager->isEnabledForAnyone(self::TOTP_APP_ID)) {
			return new JSONResponse(['message' => 'twofactor_totp is not enabled'], Http::STATUS_BAD_REQUEST);
		}

		return new JSONResponse($this->totpImporter->cleanUpDuplicates());
	}

	/**
	 * Current twofactor_totp migration state, so the admin UI can refresh its
	 * banners live after changes (import / disable / provision).
	 */
	public function totpStatus(): JSONResponse {
		$enabled = $this->appManager->isEnabledForAnyone(self::TOTP_APP_ID);

		return new JSONResponse([
			'enabled' => $enabled,
			'importCount' => $enabled ? $this->totpImporter->available() : 0,
			'duplicateUsers' => $enabled ? $this->totpImporter->duplicateUserIds() : [],
		]);
	}

	/**
	 * Disable OATH for the given managed users: delete each secret and remove the
	 * provider registration (same effect as `occ twofactorauth:disable <uid> oath`).
	 *
	 * @param string[] $users
	 */
	public function disableUsers(array $users): JSONResponse {
		$results = [];
		foreach ($users as $uid) {
			$uid = (string)$uid;
			$user = $this->userManager->get($uid);
			if ($user === null) {
				$results[] = ['username' => $uid, 'status' => 'error', 'message' => 'unknown user'];
				continue;
			}
			if (!$this->policyService->isManaged($user)) {
				$results[] = ['username' => $uid, 'status' => 'error', 'message' => 'not managed'];
				continue;
			}
			$this->otpService->disable($uid);
			$this->registry->disableProviderFor($this->provider, $user);
			$results[] = ['username' => $uid, 'status' => 'none'];
		}

		return new JSONResponse(['results' => $results]);
	}

	public function setSecretLength(int $length): JSONResponse {
		// $length is in bytes (key material) and must be one of the offered presets,
		// so an invalid Base32 length can never be stored as the default.
		if (!in_array($length, Constants::SECRET_PRESET_BYTES, true)) {
			return new JSONResponse(['message' => 'Secret length must be one of the presets'], Http::STATUS_BAD_REQUEST);
		}
		$this->appConfig->setValueInt(Application::APP_ID, Constants::CONFIG_SECRET_LENGTH, $length);

		return new JSONResponse(['length' => $length]);
	}

	public function setManagedGroups(array $groups): JSONResponse {
		$gids = array_values(array_filter($groups, is_string(...)));
		$this->appConfig->setValueArray(Application::APP_ID, Constants::CONFIG_MANAGED_GROUPS, $gids);

		return new JSONResponse(['groups' => $gids]);
	}

	public function setExcludedGroups(array $groups): JSONResponse {
		$gids = array_values(array_filter($groups, is_string(...)));
		$this->appConfig->setValueArray(Application::APP_ID, Constants::CONFIG_EXCLUDED_GROUPS, $gids);

		return new JSONResponse(['groups' => $gids]);
	}

	/** All admin-managed users with their current status (for the inline table). */
	public function managedUsers(): JSONResponse {
		$users = array_map($this->userRow(...), $this->policyService->listManagedUsers());

		return new JSONResponse(['users' => array_values($users)]);
	}

	/** Same data as managedUsers(), but as a downloadable CSV. */
	#[NoCSRFRequired]
	/**
	 * Export the managed users as CSV. With `secrets=1` the current (decrypted)
	 * secret of each stored token (pending or enabled, like the "Show" action)
	 * is included, so the export can serve as a portable backup that
	 * re-provisions the same tokens; otherwise the secret column is left empty.
	 */
	public function exportUsers(string $secrets = '0'): DataDownloadResponse {
		$includeSecrets = $secrets === '1';
		$lines = ['username,status,type,algorithm,digits,period,counter,challenge,suite,secret'];
		foreach ($this->policyService->listManagedUsers() as $user) {
			$row = $this->userRow($user);
			$type = $row['type'];
			// Leave fields that do not apply to this type empty (self-documenting CSV).
			$period = $type === Constants::TYPE_NAMES[Constants::TYPE_TOTP] ? (string)$row['period'] : '';
			$counter = $type === Constants::TYPE_NAMES[Constants::TYPE_HOTP] ? (string)$row['counter'] : '';
			$challenge = $type === Constants::TYPE_NAMES[Constants::TYPE_OCRA] ? (string)$row['challenge'] : '';
			$suite = $type === Constants::TYPE_NAMES[Constants::TYPE_OCRA] ? (string)$row['suite'] : '';
			$secret = '';
			if ($includeSecrets) {
				$entity = $this->otpService->findByUserId($user->getUID());
				if ($entity !== null) {
					$secret = $this->otpService->decryptSecret($entity);
				}
			}
			$lines[] = implode(',', [$row['username'], $row['status'], $row['type'], $row['algorithm'], (string)$row['digits'], $period, $counter, $challenge, $suite, $secret]);
		}

		return new DataDownloadResponse(implode("\n", $lines) . "\n", 'twofactor_oath_users.csv', 'text/csv');
	}

	/**
	 * Provision (create + enable + lock) OTP tokens from a pasted/serialized CSV.
	 * Columns: username,status,type,algorithm,digits,period,counter,challenge,suite,secret
	 * (status is ignored; an empty secret means a random one is generated; the suite
	 * is used for type=ocra and already encodes the challenge length).
	 */
	public function provision(string $data): JSONResponse {
		$results = [];
		foreach (preg_split('/\r\n|\r|\n/', trim($data)) ?: [] as $line) {
			$line = trim($line);
			if ($line === '') {
				continue;
			}
			$cols = str_getcsv($line);
			$username = trim((string)($cols[0] ?? ''));
			if ($username === '' || strtolower($username) === 'username') {
				continue;
			}

			$user = $this->userManager->get($username);
			if ($user === null) {
				$results[] = ['username' => $username, 'status' => 'error', 'message' => 'unknown user'];
				continue;
			}
			if (!$this->policyService->isManaged($user)) {
				$results[] = ['username' => $username, 'status' => 'error', 'message' => 'not managed'];
				continue;
			}

			$type = $this->otpService->typeFromName(trim((string)($cols[2] ?? ''))) ?? Constants::DEFAULT_TYPE;
			$algorithm = $this->otpService->algorithmFromName(trim((string)($cols[3] ?? ''))) ?? Constants::DEFAULT_ALGORITHM;
			$digits = (int)(trim((string)($cols[4] ?? '')) ?: Constants::DEFAULT_DIGITS);
			$period = (int)(trim((string)($cols[5] ?? '')) ?: Constants::DEFAULT_PERIOD);
			$counter = (int)(trim((string)($cols[6] ?? '')) ?: Constants::DEFAULT_COUNTER);
			// cols[7] = challenge length (informational; encoded in the OCRA suite).
			$suite = trim((string)($cols[8] ?? ''));
			$secret = trim((string)($cols[9] ?? ''));

			try {
				$entity = $this->otpService->createSecret(
					$username,
					type: $type,
					algorithm: $algorithm,
					digits: $digits,
					period: $period,
					customSecret: $secret !== '' ? $secret : null,
					state: Constants::STATE_ENABLED,
					locked: true,
					counter: $counter,
					suite: $suite !== '' ? $suite : null,
				);
				$this->registry->enableProviderFor($this->provider, $user);
				$results[] = [
					'username' => $username,
					'status' => 'provisioned',
					'secret' => $this->otpService->decryptSecret($entity),
					'uri' => $entity->isOcra() ? '' : $this->otpService->getProvisioningUri($entity, $user->getCloudId(), $this->defaults->getName()),
				];
			} catch (InvalidArgumentException $e) {
				$results[] = ['username' => $username, 'status' => 'error', 'message' => $e->getMessage()];
			}
		}

		return new JSONResponse(['results' => $results]);
	}

	/** Reveal the current (stored) secret + provisioning URI of a user without changing it. */
	public function showToken(string $username): JSONResponse {
		$user = $this->userManager->get($username);
		if ($user === null) {
			return new JSONResponse(['message' => 'unknown user'], Http::STATUS_NOT_FOUND);
		}
		try {
			$entity = $this->mapper->getByUserId($username);
		} catch (DoesNotExistException) {
			return new JSONResponse(['message' => 'no token'], Http::STATUS_NOT_FOUND);
		}

		return new JSONResponse([
			'username' => $username,
			'secret' => $this->otpService->decryptSecret($entity),
			'uri' => $entity->isOcra() ? '' : $this->otpService->getProvisioningUri($entity, $user->getCloudId(), $this->defaults->getName()),
		]);
	}

	/**
	 * Current status + configuration of a single managed user, with name-coded
	 * type/algorithm ready for the table and CSV.
	 *
	 * @return array{username: string, status: string, type: string, algorithm: string, digits: int, period: int, counter: int, challenge: int, suite: string}
	 */
	private function userRow(IUser $user): array {
		$uid = $user->getUID();
		try {
			$secret = $this->mapper->getByUserId($uid);
			$suite = $secret->getSuite() ?? '';

			return [
				'username' => $uid,
				'status' => $secret->isEnabled() ? 'enabled' : 'pending',
				'type' => Constants::TYPE_NAMES[$secret->getType()],
				'algorithm' => Constants::ALGORITHM_DIGESTS[$secret->getAlgorithm()],
				'digits' => $secret->getDigits(),
				'period' => $secret->getPeriod(),
				'counter' => $secret->getCounter(),
				'challenge' => $secret->isOcra() ? $this->challengeFromSuite($suite) : 0,
				'suite' => $suite,
			];
		} catch (DoesNotExistException) {
			return [
				'username' => $uid,
				'status' => 'none',
				'type' => Constants::TYPE_NAMES[Constants::DEFAULT_TYPE],
				'algorithm' => Constants::ALGORITHM_DIGESTS[Constants::DEFAULT_ALGORITHM],
				'digits' => Constants::DEFAULT_DIGITS,
				'period' => Constants::DEFAULT_PERIOD,
				'counter' => Constants::DEFAULT_COUNTER,
				'challenge' => 0,
				'suite' => '',
			];
		}
	}

	/** Numeric challenge length (QN) encoded in an OCRA suite, or 0 if none. */
	private function challengeFromSuite(string $suite): int {
		return preg_match('/:QN(\d+)/', $suite, $m) === 1 ? (int)$m[1] : 0;
	}
}
