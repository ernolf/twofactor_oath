<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorOath\Tests\Unit;

use OCA\TwoFactorOath\Service\Ocra;
use PHPUnit\Framework\TestCase;

class OcraTest extends TestCase {
	private Ocra $ocra;

	protected function setUp(): void {
		$this->ocra = new Ocra();
	}

	/**
	 * RFC 6287 Appendix C, one-way challenge-response:
	 * OCRA-1:HOTP-SHA1-6:QN08 with the standard 20-byte key.
	 *
	 * @dataProvider qn08Provider
	 */
	public function testRfc6287Sha1Qn08(string $question, string $expected): void {
		// Standard key from RFC 6287: ASCII "12345678901234567890" (20 bytes).
		$key = '12345678901234567890';

		$this->assertSame($expected, $this->ocra->generate('OCRA-1:HOTP-SHA1-6:QN08', $key, $question));
	}

	/**
	 * @return array<int, array{0: string, 1: string}>
	 */
	public static function qn08Provider(): array {
		return [
			['00000000', '237653'],
			['11111111', '243178'],
			['22222222', '653583'],
			['33333333', '740991'],
			['44444444', '608993'],
			['55555555', '388898'],
			['66666666', '816933'],
			['77777777', '224598'],
			['88888888', '750600'],
			['99999999', '294470'],
		];
	}
}
