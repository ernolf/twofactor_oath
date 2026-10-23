<?php

/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

script('twofactor_oath', 'twofactor_oath-settings');
style('twofactor_oath', 'twofactor_oath-settings');
?>

<div id="twofactor-oath-settings"></div>
<?php if ($_['hideBackupCodes'] ?? false) {
	// twofactor_backupcodes renders its section in the same list and offers no way to hide it.
	?>
<style>#two-factor-auth li:has(#twofactor-backupcodes-settings) { display: none; }</style>
<?php } ?>
