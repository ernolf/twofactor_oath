<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorOath\AppInfo;

use OCA\TwoFactorOath\Db\IOtpSecretMapper;
use OCA\TwoFactorOath\Db\OtpSecretMapper;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use Override;

final class Application extends App implements IBootstrap {
	public const APP_ID = 'twofactor_oath';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	#[Override]
	public function register(IRegistrationContext $context): void {
		// Third-party dependencies (otphp, …) are vendored via composer.
		$autoload = __DIR__ . '/../../vendor/autoload.php';
		if (is_file($autoload)) {
			require_once $autoload;
		}

		// OtpService depends on the IOtpSecretMapper abstraction (mockable in tests).
		$context->registerServiceAlias(IOtpSecretMapper::class, OtpSecretMapper::class);

		// The 2FA provider is registered declaratively via appinfo/info.xml
		// (<two-factor-providers>); no programmatic registration is needed here.
	}

	#[Override]
	public function boot(IBootContext $context): void {
	}
}
