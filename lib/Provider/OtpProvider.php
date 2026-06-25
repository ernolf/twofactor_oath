<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorOath\Provider;

use OCA\TwoFactorOath\AppInfo\Application;
use OCA\TwoFactorOath\Constants;
use OCA\TwoFactorOath\Db\OtpSecretMapper;
use OCA\TwoFactorOath\Service\OtpService;
use OCA\TwoFactorOath\Service\PolicyService;
use OCA\TwoFactorOath\Settings\PersonalSettings;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Services\IInitialState;
use OCP\Authentication\TwoFactorAuth\IActivatableAtLogin;
use OCP\Authentication\TwoFactorAuth\IDeactivatableByAdmin;
use OCP\Authentication\TwoFactorAuth\ILoginSetupProvider;
use OCP\Authentication\TwoFactorAuth\IPersonalProviderSettings;
use OCP\Authentication\TwoFactorAuth\IProvider;
use OCP\Authentication\TwoFactorAuth\IProvidesIcons;
use OCP\Authentication\TwoFactorAuth\IProvidesPersonalSettings;
use OCP\IL10N;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Template\ITemplate;
use OCP\Template\ITemplateManager;
use Override;

final class OtpProvider implements IProvider, IProvidesIcons, IProvidesPersonalSettings, IActivatableAtLogin, IDeactivatableByAdmin {
	public function __construct(
		private readonly OtpSecretMapper $mapper,
		private readonly OtpService $otpService,
		private readonly PolicyService $policyService,
		private readonly ITemplateManager $templateManager,
		private readonly IInitialState $initialState,
		private readonly IL10N $l10n,
		private readonly IRequest $request,
		private readonly ISession $session,
		private readonly IURLGenerator $urlGenerator,
	) {
	}

	/** Session key holding the challenge issued for an OCRA login. */
	private const OCRA_LOGIN_CHALLENGE = 'twofactor_oath_login_challenge';

	/** Session flag: a HOTP code attempt failed (enables the resync UI). */
	private const HOTP_FAILED = 'twofactor_oath_hotp_failed';

	#[Override]
	public function getId(): string {
		return 'oath';
	}

	#[Override]
	public function getLightIcon(): string {
		return $this->urlGenerator->imagePath(Application::APP_ID, 'app.svg');
	}

	#[Override]
	public function getDarkIcon(): string {
		return $this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg');
	}

	#[Override]
	public function getDisplayName(): string {
		return 'OATH (TOTP/HOTP/OCRA)';
	}

	#[Override]
	public function getDescription(): string {
		return $this->l10n->t('Authenticate with an OATH-compliant app or hardware token');
	}

	#[Override]
	public function getTemplate(IUser $user): ITemplate {
		$template = $this->templateManager->getTemplate(Application::APP_ID, 'challenge');
		try {
			$secret = $this->mapper->getByUserId($user->getUID());
			$template->assign('method', strtoupper(Constants::TYPE_NAMES[$secret->getType()]));
			if ($secret->isOcra()) {
				$challenge = $this->otpService->generateOcraChallenge($secret);
				$this->session->set(self::OCRA_LOGIN_CHALLENGE, $challenge);
				$template->assign('ocraChallenge', $challenge);
			} elseif ($secret->isHotp()) {
				// Offer the resync UI only after a previous code attempt failed.
				$template->assign('showResync', (bool)$this->session->get(self::HOTP_FAILED));
			}
		} catch (DoesNotExistException) {
		}

		return $template;
	}

	#[Override]
	public function verifyChallenge(IUser $user, string $challenge): bool {
		try {
			$secret = $this->mapper->getByUserId($user->getUID());
		} catch (DoesNotExistException) {
			return false;
		}
		if (!$secret->isEnabled()) {
			return false;
		}

		// OCRA: verify the response against the challenge shown in getTemplate().
		if ($secret->isOcra()) {
			$question = (string)$this->session->get(self::OCRA_LOGIN_CHALLENGE);
			if ($question === '') {
				return false;
			}
			$ok = $this->otpService->verifyOcra($secret, $question, $challenge);
			if ($ok) {
				$this->session->remove(self::OCRA_LOGIN_CHALLENGE);
			}
			return $ok;
		}

		// HOTP: a second consecutive code (in the optional "otp_resync" field)
		// re-synchronizes a drifted counter (RFC 4226 §7.4). The resync UI is only
		// shown after a failed attempt, tracked via a session flag.
		if ($secret->isHotp()) {
			$resync = trim((string)$this->request->getParam('otp_resync', ''));
			$ok = $resync !== ''
				? $this->otpService->resyncHotp($secret, $challenge, $resync)
				: $this->otpService->verify($secret, $challenge);
			if ($ok) {
				$this->session->remove(self::HOTP_FAILED);
			} else {
				$this->session->set(self::HOTP_FAILED, true);
			}
			return $ok;
		}

		return $this->otpService->verify($secret, $challenge);
	}

	#[Override]
	public function isTwoFactorAuthEnabledForUser(IUser $user): bool {
		try {
			return $this->mapper->getByUserId($user->getUID())->isEnabled();
		} catch (DoesNotExistException) {
			return false;
		}
	}

	#[Override]
	public function disableFor(IUser $user) {
		// Admin-initiated disable (e.g. `occ twofactorauth:disable <uid> oath`):
		// remove the user's OATH token. The provider registry entry is cleared by
		// the caller (ProviderManager) right after this returns.
		$this->otpService->disable($user->getUID());
	}

	#[Override]
	public function getPersonalSettings(IUser $user): IPersonalProviderSettings {
		$this->initialState->provideInitialState(
			'state',
			$this->otpService->hasEnabledSecret($user->getUID())
				? Constants::STATE_ENABLED
				: Constants::STATE_DISABLED,
		);
		$this->initialState->provideInitialState('managed', $this->policyService->isManaged($user));

		return new PersonalSettings($this->templateManager);
	}

	#[Override]
	public function getLoginSetup(IUser $user): ILoginSetupProvider {
		return new AtLoginProvider(
			$this->templateManager,
			$this->initialState,
			$this->policyService->isManaged($user),
		);
	}
}
