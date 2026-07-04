<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorOath\Controller;

use InvalidArgumentException;
use OCA\TwoFactorOath\Constants;
use OCA\TwoFactorOath\Service\IOtpService;
use OCA\TwoFactorOath\Service\IPolicyService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\PasswordConfirmationRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Authentication\TwoFactorAuth\ALoginSetupController;
use OCP\Authentication\TwoFactorAuth\IProvider;
use OCP\Authentication\TwoFactorAuth\IRegistry;
use OCP\Defaults;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUser;
use OCP\IUserSession;
use RuntimeException;

class SettingsController extends ALoginSetupController {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly IUserSession $userSession,
		private readonly ISession $session,
		private readonly IOtpService $otpService,
		private readonly IPolicyService $policyService,
		private readonly IRegistry $registry,
		private readonly IProvider $provider,
		private readonly Defaults $defaults,
	) {
		parent::__construct($appName, $request);
	}

	/** Session key holding the challenge issued during OCRA self-enrollment. */
	private const OCRA_SETUP_CHALLENGE = 'twofactor_oath_setup_challenge';

	/**
	 * Drive the enrollment state machine:
	 *  - STATE_DISABLED: remove the user's secret
	 *  - STATE_CREATED:  generate a new secret, return it plus the provisioning URI
	 *  - STATE_ENABLED:  verify the given code and activate the secret
	 */
	#[NoAdminRequired]
	#[PasswordConfirmationRequired]
	#[BruteForceProtection('otp_enable')]
	public function enable(
		int $state,
		?string $code = null,
		?int $type = null,
		?int $algorithm = null,
		?int $digits = null,
		?int $period = null,
		?string $secret = null,
		?int $counter = null,
		?int $epoch = null,
		?string $suite = null,
	): JSONResponse {
		$user = $this->getUser();
		if ($this->policyService->isManaged($user)) {
			return new JSONResponse(['message' => 'OTP is managed by an administrator'], Http::STATUS_FORBIDDEN);
		}
		$uid = $user->getUID();

		switch ($state) {
			case Constants::STATE_DISABLED:
				$this->otpService->disable($uid);
				$this->registry->disableProviderFor($this->provider, $user);
				return new JSONResponse(['state' => Constants::STATE_DISABLED]);
			case Constants::STATE_CREATED:
				try {
					$entity = $this->otpService->createSecret(
						$uid,
						type: $type ?? Constants::DEFAULT_TYPE,
						algorithm: $algorithm ?? Constants::DEFAULT_ALGORITHM,
						digits: $digits ?? Constants::DEFAULT_DIGITS,
						period: $period ?? Constants::DEFAULT_PERIOD,
						customSecret: $secret,
						counter: $counter ?? Constants::DEFAULT_COUNTER,
						epoch: $epoch ?? Constants::DEFAULT_EPOCH,
						suite: $suite,
					);
				} catch (InvalidArgumentException $e) {
					return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
				}
				if ($entity->isOcra()) {
					// OCRA enrollment is confirmed by a challenge-response, not a code.
					$challenge = $this->otpService->generateOcraChallenge($entity);
					$this->session->set(self::OCRA_SETUP_CHALLENGE, $challenge);
					return new JSONResponse([
						'state' => Constants::STATE_CREATED,
						'secret' => $this->otpService->decryptSecret($entity),
						'challenge' => $challenge,
					]);
				}
				return new JSONResponse([
					'state' => Constants::STATE_CREATED,
					'secret' => $this->otpService->decryptSecret($entity),
					'qrUrl' => $this->otpService->getProvisioningUri($entity, $user->getCloudId(), $this->defaults->getName()),
				]);
			case Constants::STATE_ENABLED:
				if ($code === null) {
					throw new InvalidArgumentException('code is missing');
				}
				$entity = $this->otpService->findByUserId($uid);
				if ($entity !== null && $entity->isOcra()) {
					$challenge = (string)$this->session->get(self::OCRA_SETUP_CHALLENGE);
					$success = $challenge !== '' && $this->otpService->enableOcra($uid, $challenge, $code);
					if ($success) {
						$this->session->remove(self::OCRA_SETUP_CHALLENGE);
					}
				} else {
					$success = $this->otpService->enable($uid, $code);
				}
				if ($success) {
					$this->registry->enableProviderFor($this->provider, $user);
				}
				$response = new JSONResponse([
					'state' => $success ? Constants::STATE_ENABLED : Constants::STATE_CREATED,
				]);
				if (!$success) {
					$response->throttle();
				}
				return $response;
			default:
				throw new InvalidArgumentException('Invalid OTP state');
		}
	}

	/**
	 * Re-synchronize a drifted HOTP counter from two consecutive codes.
	 * No password confirmation is required: two valid consecutive codes already
	 * prove possession of the token.
	 */
	#[NoAdminRequired]
	#[BruteForceProtection('otp_resync')]
	public function resync(string $code1, string $code2): JSONResponse {
		$user = $this->getUser();
		if ($this->policyService->isManaged($user)) {
			return new JSONResponse(['message' => 'OTP is managed by an administrator'], Http::STATUS_FORBIDDEN);
		}

		$success = $this->otpService->resyncHotpForUser($user->getUID(), $code1, $code2);
		$response = new JSONResponse(['success' => $success]);
		if (!$success) {
			$response->throttle();
		}

		return $response;
	}

	/**
	 * The user's own non-sensitive configuration (type/algorithm/digits/period/
	 * counter). No password needed: this reveals nothing that could clone the
	 * token. Returns null if there is no enabled token.
	 */
	#[NoAdminRequired]
	public function config(): JSONResponse {
		$entity = $this->otpService->findByUserId($this->getUser()->getUID());
		if ($entity === null || !$entity->isEnabled()) {
			return new JSONResponse(['config' => null]);
		}

		return new JSONResponse([
			'config' => [
				'type' => Constants::TYPE_NAMES[$entity->getType()],
				'algorithm' => Constants::ALGORITHM_DIGESTS[$entity->getAlgorithm()],
				'digits' => $entity->getDigits(),
				'period' => $entity->getPeriod(),
				'counter' => $entity->getCounter(),
				'suite' => $entity->getSuite(),
			],
		]);
	}

	/**
	 * Reveal the user's own current configuration, secret and provisioning URI
	 * (for re-displaying the QR code). Strict password confirmation forces a fresh
	 * password on *every* call (no grace window): revealing the secret equals
	 * cloning the token, so it must stay a deliberate act. Not available to
	 * admin-managed users.
	 */
	#[NoAdminRequired]
	#[PasswordConfirmationRequired(strict: true)]
	public function show(): JSONResponse {
		$user = $this->getUser();
		if ($this->policyService->isManaged($user)) {
			return new JSONResponse(['message' => 'OTP is managed by an administrator'], Http::STATUS_FORBIDDEN);
		}

		$entity = $this->otpService->findByUserId($user->getUID());
		if ($entity === null || !$entity->isEnabled()) {
			return new JSONResponse(['message' => 'no token'], Http::STATUS_NOT_FOUND);
		}

		return new JSONResponse([
			'type' => Constants::TYPE_NAMES[$entity->getType()],
			'algorithm' => Constants::ALGORITHM_DIGESTS[$entity->getAlgorithm()],
			'digits' => $entity->getDigits(),
			'period' => $entity->getPeriod(),
			'counter' => $entity->getCounter(),
			'secret' => $this->otpService->decryptSecret($entity),
			'uri' => $entity->isOcra() ? '' : $this->otpService->getProvisioningUri($entity, $user->getCloudId(), $this->defaults->getName()),
		]);
	}

	/**
	 * Disable the user's OTP. Strict password confirmation forces a fresh password
	 * on *every* call (no grace): disabling 2FA is security-decreasing, so it must
	 * be a deliberate, freshly confirmed action — unlike enabling, which keeps the
	 * usual grace window.
	 */
	#[NoAdminRequired]
	#[PasswordConfirmationRequired(strict: true)]
	public function deactivate(): JSONResponse {
		$user = $this->getUser();
		if ($this->policyService->isManaged($user)) {
			return new JSONResponse(['message' => 'OTP is managed by an administrator'], Http::STATUS_FORBIDDEN);
		}

		$this->otpService->disable($user->getUID());
		$this->registry->disableProviderFor($this->provider, $user);

		return new JSONResponse(['state' => Constants::STATE_DISABLED]);
	}

	private function getUser(): IUser {
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new RuntimeException('No user in this context');
		}

		return $user;
	}
}
