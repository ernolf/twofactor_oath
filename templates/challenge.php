<?php

/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/** @var \OCP\IL10N $l */
/** @var array $_ */
?>

<?php if (!empty($_['method'])): ?>
	<p class="otp-method"><strong><?php p('OATH (' . $_['method'] . ')') ?></strong></p>
<?php endif; ?>

<?php if (!empty($_['ocraChallenge'])): ?>

	<p><?php p($l->t('Challenge-response: type the challenge below into your OCRA token, then enter the response it shows.')) ?></p>
	<p class="otp-ocra-challenge"><strong><?php p($_['ocraChallenge']) ?></strong></p>

	<form method="POST" class="otp-form">
		<input type="text" name="challenge" required="required" autofocus autocomplete="off" inputmode="numeric" autocapitalize="off" placeholder="<?php p($l->t('Response')) ?>">
		<button class="primary" type="submit">
			<?php p($l->t('Submit')); ?>
		</button>
	</form>

<?php else: ?>

	<p><?php p($l->t('Enter the one-time code from your OTP app or token.')) ?></p>

	<form method="POST" class="otp-form">
		<input type="text" minlength="6" maxlength="10" name="challenge" required="required" autofocus autocomplete="one-time-code" inputmode="numeric" autocapitalize="off" placeholder="<?php p($l->t('Authentication code')) ?>">
		<?php if (!empty($_['showResync'])): ?>
			<details class="otp-resync">
				<summary tabindex="-1"><?php p($l->t('My HOTP token is out of sync')) ?></summary>
				<p><?php p($l->t('Enter your current code in the field above, then the very next code from your token here, and submit.')) ?></p>
				<input type="text" minlength="6" maxlength="10" name="otp_resync" autocomplete="off" inputmode="numeric" autocapitalize="off" placeholder="<?php p($l->t('Next code')) ?>">
			</details>
		<?php endif; ?>
		<button class="primary" type="submit">
			<?php p($l->t('Submit')); ?>
		</button>
	</form>

<?php endif; ?>
