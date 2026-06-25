<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorOath\Provider;

use OCA\TwoFactorOath\AppInfo\Application;
use OCP\AppFramework\Services\IInitialState;
use OCP\Authentication\TwoFactorAuth\ILoginSetupProvider;
use OCP\Template\ITemplate;
use OCP\Template\ITemplateManager;
use Override;

/**
 * Renders the at-login setup view: a user who must use 2FA but has nothing
 * configured yet is guided through generating a token on first login. Managed
 * users get a notice instead (their token is provisioned by an administrator).
 */
final class AtLoginProvider implements ILoginSetupProvider {
	public function __construct(
		private readonly ITemplateManager $templateManager,
		private readonly IInitialState $initialState,
		private readonly bool $managed,
	) {
	}

	#[Override]
	public function getBody(): ITemplate {
		$this->initialState->provideInitialState('managed', $this->managed);

		return $this->templateManager->getTemplate(Application::APP_ID, 'loginsetup');
	}
}
