<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorOath\Db;

use OCA\TwoFactorOath\Constants;
use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method int getType()
 * @method void setType(int $type)
 * @method string getSecret()
 * @method void setSecret(string $secret)
 * @method int getAlgorithm()
 * @method void setAlgorithm(int $algorithm)
 * @method int getDigits()
 * @method void setDigits(int $digits)
 * @method int getPeriod()
 * @method void setPeriod(int $period)
 * @method int getCounter()
 * @method void setCounter(int $counter)
 * @method int getEpoch()
 * @method void setEpoch(int $epoch)
 * @method int getState()
 * @method void setState(int $state)
 * @method bool getLocked()
 * @method void setLocked(bool $locked)
 * @method int|null getLastUsed()
 * @method void setLastUsed(?int $lastUsed)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method string|null getSuite()
 * @method void setSuite(?string $suite)
 */
final class OtpSecret extends Entity {
	protected string $userId = '';
	protected int $type = Constants::DEFAULT_TYPE;
	protected string $secret = '';
	protected int $algorithm = Constants::DEFAULT_ALGORITHM;
	protected int $digits = Constants::DEFAULT_DIGITS;
	protected int $period = Constants::DEFAULT_PERIOD;
	protected int $counter = Constants::DEFAULT_COUNTER;
	protected int $epoch = Constants::DEFAULT_EPOCH;
	protected int $state = Constants::STATE_CREATED;
	protected bool $locked = false;
	protected ?int $lastUsed = null;
	protected int $createdAt = 0;
	protected ?string $suite = null;

	public function __construct() {
		$this->addType('userId', 'string');
		$this->addType('type', 'integer');
		$this->addType('secret', 'string');
		$this->addType('algorithm', 'integer');
		$this->addType('digits', 'integer');
		$this->addType('period', 'integer');
		$this->addType('counter', 'integer');
		$this->addType('epoch', 'integer');
		$this->addType('state', 'integer');
		$this->addType('locked', 'boolean');
		$this->addType('lastUsed', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('suite', 'string');
	}

	public function isEnabled(): bool {
		return $this->state === Constants::STATE_ENABLED;
	}

	public function isHotp(): bool {
		return $this->type === Constants::TYPE_HOTP;
	}

	public function isOcra(): bool {
		return $this->type === Constants::TYPE_OCRA;
	}
}
