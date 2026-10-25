<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorOath\Middleware;

use Exception;

/** Thrown by BackupCodesMiddleware to stop a managed user from generating backup codes. */
final class BackupCodesForbiddenException extends Exception {
}
