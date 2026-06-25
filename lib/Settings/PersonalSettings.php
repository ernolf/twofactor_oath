<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorOath\Settings;

use OCA\TwoFactorOath\AppInfo\Application;
use OCP\Authentication\TwoFactorAuth\IPersonalProviderSettings;
use OCP\Template\ITemplate;
use OCP\Template\ITemplateManager;
use Override;

final class PersonalSettings implements IPersonalProviderSettings {
	public function __construct(
		private readonly ITemplateManager $templateManager,
	) {
	}

	#[Override]
	public function getBody(): ITemplate {
		return $this->templateManager->getTemplate(Application::APP_ID, 'personal');
	}
}
