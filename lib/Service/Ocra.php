<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorOath\Service;

use InvalidArgumentException;

/**
 * OATH Challenge-Response Algorithm (OCRA), RFC 6287.
 *
 * OCRA(K, DataInput) = Truncate(HMAC-SHA{1,256,512}(K, DataInput)) where
 * DataInput = OCRASuite | 0x00 | [C] | Q | [P] | [S] | [T].
 *
 * Self-contained: arbitrarily long numeric challenges are converted without
 * ext-bcmath/ext-gmp. Validated against the RFC 6287 Appendix C test vectors.
 */
final class Ocra {
	/**
	 * Compute an OCRA response.
	 *
	 * @param string $suite the OCRASuite, e.g. "OCRA-1:HOTP-SHA1-6:QN08"
	 * @param string $key the raw (binary) shared key
	 * @param string $question the challenge in the suite's Q format (decimal for QN, hex for QH, text for QA)
	 * @param int $counter counter value (only if the suite carries a C field)
	 * @param string $pinHash raw binary PIN/password hash (only if the suite carries a P field)
	 * @param string $sessionInfo raw session bytes (only if the suite carries an S field)
	 * @param int|null $timestamp number of time steps (only if the suite carries a T field)
	 *
	 * @throws InvalidArgumentException on a malformed suite
	 */
	public function generate(
		string $suite,
		string $key,
		string $question,
		int $counter = 0,
		string $pinHash = '',
		string $sessionInfo = '',
		?int $timestamp = null,
	): string {
		$parsed = $this->parseSuite($suite);

		$data = $suite . "\x00";
		if ($parsed['counter']) {
			$data .= pack('J', $counter);
		}
		$data .= $this->questionToBytes($question, $parsed['qFormat']);
		if ($parsed['pinHash'] !== null) {
			$data .= $pinHash;
		}
		if ($parsed['session'] > 0) {
			$data .= $sessionInfo;
		}
		if ($parsed['timeStep'] !== null) {
			$data .= pack('J', $timestamp ?? 0);
		}

		$hmac = hash_hmac($parsed['hash'], $data, $key, true);

		return $this->truncate($hmac, $parsed['digits']);
	}

	/**
	 * Parse an OCRASuite string into its components.
	 *
	 * @return array{hash: string, digits: int, counter: bool, qFormat: string, qLength: int, pinHash: ?string, session: int, timeStep: ?int}
	 *
	 * @throws InvalidArgumentException on a malformed suite
	 */
	public function parseSuite(string $suite): array {
		$parts = explode(':', $suite);
		if (count($parts) !== 3 || strtoupper($parts[0]) !== 'OCRA-1') {
			throw new InvalidArgumentException('Invalid OCRASuite');
		}

		$crypto = explode('-', $parts[1]);
		if (count($crypto) !== 3 || strtoupper($crypto[0]) !== 'HOTP') {
			throw new InvalidArgumentException('Invalid OCRA crypto function');
		}
		// RFC 6287 defines SHA1/256/512; we also accept SHA224/384 so that non-strict
		// setups work end-to-end (the truncation and HMAC are identical for them).
		if (!in_array(strtolower($crypto[1]), ['sha1', 'sha224', 'sha256', 'sha384', 'sha512'], true)) {
			throw new InvalidArgumentException('Unsupported OCRA hash function');
		}

		$result = [
			'hash' => strtolower($crypto[1]),
			'digits' => (int)$crypto[2],
			'counter' => false,
			'qFormat' => 'N',
			'qLength' => 8,
			'pinHash' => null,
			'session' => 0,
			'timeStep' => null,
		];

		foreach (explode('-', strtoupper($parts[2])) as $field) {
			if ($field === 'C') {
				$result['counter'] = true;
			} elseif (str_starts_with($field, 'Q')) {
				$result['qFormat'] = $field[1];
				$result['qLength'] = (int)substr($field, 2);
			} elseif (str_starts_with($field, 'P')) {
				$result['pinHash'] = strtolower(substr($field, 1));
			} elseif (str_starts_with($field, 'S')) {
				$result['session'] = (int)substr($field, 1);
			} elseif (str_starts_with($field, 'T')) {
				$result['timeStep'] = $this->parseTimeStep(substr($field, 1));
			}
		}

		return $result;
	}

	/** Convert a T-field granularity ("1M" / "30S" / "1H") to seconds. */
	private function parseTimeStep(string $spec): int {
		if (preg_match('/^(\d+)([SMH])$/', $spec, $m) !== 1) {
			throw new InvalidArgumentException('Invalid OCRA timestamp specification');
		}
		$n = (int)$m[1];

		return match ($m[2]) {
			'S' => $n,
			'M' => $n * 60,
			'H' => $n * 3600,
			default => $n,
		};
	}

	/** Encode the challenge into its fixed 128-byte field per the Q format. */
	private function questionToBytes(string $question, string $format): string {
		switch (strtoupper($format)) {
			case 'N':
				$hex = $this->decToHex($question);
				if (strlen($hex) % 2 === 1) {
					$hex .= '0';
				}
				$bytes = (string)hex2bin($hex);
				break;
			case 'H':
				$hex = $question;
				if (strlen($hex) % 2 === 1) {
					$hex .= '0';
				}
				$bytes = (string)hex2bin($hex);
				break;
			default: // 'A': alphanumeric, taken as raw bytes
				$bytes = $question;
		}

		return str_pad(substr($bytes, 0, 128), 128, "\x00", STR_PAD_RIGHT);
	}

	/** Convert an arbitrary-length decimal string to a hex string (no bcmath/gmp). */
	private function decToHex(string $dec): string {
		$dec = ltrim($dec, '0');
		if ($dec === '') {
			return '0';
		}
		$hex = '';
		while ($dec !== '0') {
			$remainder = 0;
			$quotient = '';
			$len = strlen($dec);
			for ($i = 0; $i < $len; $i++) {
				$cur = $remainder * 10 + (int)$dec[$i];
				$quotient .= (string)intdiv($cur, 16);
				$remainder = $cur % 16;
			}
			$hex = dechex($remainder) . $hex;
			$dec = ltrim($quotient, '0');
			if ($dec === '') {
				$dec = '0';
			}
		}

		return $hex;
	}

	/** HOTP-style dynamic truncation to the configured number of digits. */
	private function truncate(string $hmac, int $digits): string {
		if ($digits === 0) {
			return bin2hex($hmac);
		}
		$offset = ord($hmac[strlen($hmac) - 1]) & 0x0f;
		$binCode = ((ord($hmac[$offset]) & 0x7f) << 24)
			| ((ord($hmac[$offset + 1]) & 0xff) << 16)
			| ((ord($hmac[$offset + 2]) & 0xff) << 8)
			| (ord($hmac[$offset + 3]) & 0xff);
		// $binCode is a 31-bit value, so it never exceeds 10 decimal digits;
		// skip the modulo for >= 10 digits to stay correct on 32-bit PHP.
		$otp = $digits >= 10 ? $binCode : $binCode % (int)(10 ** $digits);

		return str_pad((string)$otp, $digits, '0', STR_PAD_LEFT);
	}
}
