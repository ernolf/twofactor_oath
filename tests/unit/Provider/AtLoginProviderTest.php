<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorOath\Tests\Unit\Provider;

use OCA\TwoFactorOath\Provider\AtLoginProvider;
use OCP\AppFramework\Services\IInitialState;
use OCP\Template\ITemplate;
use OCP\Template\ITemplateManager;
use PHPUnit\Framework\TestCase;

final class AtLoginProviderTest extends TestCase {
	public function testGetBodyPublishesTheManagedFlagAndReturnsTheTemplate(): void {
		$template = $this->createMock(ITemplate::class);
		$templateManager = $this->createMock(ITemplateManager::class);
		$templateManager->method('getTemplate')->willReturn($template);
		$initialState = $this->createMock(IInitialState::class);
		$initialState->expects($this->once())
			->method('provideInitialState')
			->with('managed', true);

		$provider = new AtLoginProvider($templateManager, $initialState, true);

		$this->assertSame($template, $provider->getBody());
	}
}
