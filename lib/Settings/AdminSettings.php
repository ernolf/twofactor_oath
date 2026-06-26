<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorOath\Settings;

use OCA\TwoFactorOath\AppInfo\Application;
use OCA\TwoFactorOath\Constants;
use OCA\TwoFactorOath\Service\TotpImporter;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IAppConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\Settings\ISettings;
use Override;

class AdminSettings implements ISettings {
	private const TOTP_APP_ID = 'twofactor_totp';

	public function __construct(
		private readonly IInitialState $initialState,
		private readonly IAppConfig $appConfig,
		private readonly IGroupManager $groupManager,
		private readonly TotpImporter $totpImporter,
		private readonly IAppManager $appManager,
	) {
	}

	#[Override]
	public function getForm(): TemplateResponse {
		// Secret length in bytes (key material); the UI offers fixed presets. Snap a
		// stored value that is not a preset (e.g. a pre-release char-based value) to
		// the default so the preset dropdown always shows a valid selection.
		$storedBytes = $this->appConfig->getValueInt(Application::APP_ID, Constants::CONFIG_SECRET_LENGTH, Constants::SECRET_BYTES_DEFAULT);
		$this->initialState->provideInitialState(
			'secret_length',
			in_array($storedBytes, Constants::SECRET_PRESET_BYTES, true) ? $storedBytes : Constants::SECRET_BYTES_DEFAULT,
		);

		$this->initialState->provideInitialState('managed_groups', $this->appConfig->getValueArray(Application::APP_ID, Constants::CONFIG_MANAGED_GROUPS));
		$this->initialState->provideInitialState('excluded_groups', $this->appConfig->getValueArray(Application::APP_ID, Constants::CONFIG_EXCLUDED_GROUPS));

		$groups = array_map(
			static fn (IGroup $group): array => ['id' => $group->getGID(), 'label' => $group->getDisplayName()],
			$this->groupManager->search(''),
		);
		$this->initialState->provideInitialState('all_groups', $groups);

		// Import is only offered while twofactor_totp is enabled. "importable" =
		// enabled twofactor_totp accounts whose user has no OATH token yet (these
		// are cleaned up by the import). "duplicate" = users registered with both
		// apps (have OATH and still a twofactor_totp account); their twofactor_totp
		// registration should be removed so it cannot break login once disabled.
		$totpEnabled = $this->appManager->isEnabledForAnyone(self::TOTP_APP_ID);
		$this->initialState->provideInitialState('totp_enabled', $totpEnabled);
		$this->initialState->provideInitialState('totp_import_count', $totpEnabled ? $this->totpImporter->available() : 0);
		$this->initialState->provideInitialState('totp_duplicate_users', $totpEnabled ? $this->totpImporter->duplicateUserIds() : []);

		return new TemplateResponse(Application::APP_ID, 'admin');
	}

	#[Override]
	public function getSection(): string {
		return 'security';
	}

	#[Override]
	public function getPriority(): int {
		return 50;
	}
}
