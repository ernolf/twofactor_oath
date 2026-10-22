<!--
  SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
  SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Screenshots

The `<screenshot>` entries in `appinfo/info.xml`, of the current and of every released version, point to the files in this directory, so they stay here. The [wiki](https://github.com/ernolf/twofactor_oath/wiki) keeps its own copies in its `images/` directory; a retaken screenshot goes into both places.

| File | Shows | In info.xml |
| --- | --- | :-: |
| `2fa-chooser.png` | The login second-factor list with the OATH token entry and its icon | yes |
| `personal-setup.png` | Personal settings: a generated secret and the QR code, with the verify field | yes |
| `personal-advanced.png` | Advanced settings: type, algorithm, digits, period, and the Strict RFC switch | yes |
| `personal-ocra.png` | OCRA setup: the challenge and the resulting suite | |
| `personal-config.png` | The configuration summary with the Show secret and QR code button | |
| `personal-show.png` | The revealed secret and QR with the auto-hide countdown | |
| `personal-managed.png` | A managed user's personal view: the note that the token is administrator-managed | |
| `admin-provisioning.png` | The admin bulk provisioning table with the sort and filter toolbar | yes |
| `admin-status-filter.png` | The status filter open, with freshly provisioned rows shown as “provisioned” | |
| `admin-provisioning-show.png` | Provisioned tokens filtered to “Provisioned”, with secrets and QR codes revealed via Show | |
| `admin-replace-warning.png` | The confirmation dialog shown before provisioning over an existing token | |
| `admin-disable-confirm.png` | The confirmation dialog before disabling the selected tokens | |
| `admin-csv-export-secrets.png` | The dialog asking whether to include the secrets in the CSV export | |
| `admin-csv-import.png` | The Import from CSV (paste) box with rows pasted, before applying | |
| `admin-csv-applied.png` | After Apply: pasted rows checked and applied, filtered to Selected | |
| `admin-csv-provisioned.png` | After Provision: the tokens provisioned, with secrets revealed via Show | |
| `admin-totp-import.png` | The banner offering to import accounts from twofactor_totp | |
| `admin-totp-duplicates.png` | The banner for users registered with both apps: per-user occ commands and the opt-in removal button | |
| `admin-totp-duplicates-confirm.png` | The confirmation dialog before the app removes the twofactor_totp registrations | |
| `admin-totp-import-success.png` | The confirmation toast after a successful import, with the banner recommending to disable twofactor_totp | |
| `login-ocra.png` | The OCRA challenge shown at the login prompt | |
| `login-setup-chooser.png` | The provider chooser shown during enforced login setup | |
| `login-setup.png` | OATH enrollment during login (TOTP default) with secret and QR | |
| `login-setup-ocra.png` | OATH login enrollment with Advanced settings open on OCRA | |
