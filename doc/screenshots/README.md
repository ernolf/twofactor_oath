<!--
  SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
  SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Screenshots

Place the PNG files listed below in this directory. They are referenced by the README, the documentation pages and `appinfo/info.xml`. Use clean, cropped shots (no browser chrome where avoidable).

| File | Shows | Used in |
| --- | --- | --- |
| `2fa-chooser.png` | The login second-factor list with the OATH token entry and its icon | README, info.xml |
| `personal-setup.png` | Personal settings: a generated secret and the QR code, with the verify field | README, info.xml |
| `personal-advanced.png` | Advanced settings: type, algorithm, digits, period, and the Strict RFC switch | README, info.xml |
| `personal-ocra.png` | OCRA setup: the challenge and the resulting suite | README, ocra.md |
| `personal-config.png` | The configuration summary with the Show secret and QR code button | security.md |
| `personal-show.png` | The revealed secret and QR with the auto-hide countdown | security.md |
| `personal-managed.png` | A managed user's personal view: the note that the token is administrator-managed | admin-guide.md |
| `admin-provisioning.png` | The admin bulk provisioning table with the sort and filter toolbar | README, admin-guide.md, info.xml |
| `admin-status-filter.png` | The status filter open, with freshly provisioned rows shown as “provisioned” | admin-guide.md |
| `admin-provisioning-show.png` | Provisioned tokens filtered to “Provisioned”, with secrets and QR codes revealed via Show | admin-guide.md |
| `admin-replace-warning.png` | The confirmation dialog shown before provisioning over an existing token | admin-guide.md |
| `admin-disable-confirm.png` | The confirmation dialog before disabling the selected tokens | admin-guide.md |
| `admin-csv-export-secrets.png` | The dialog asking whether to include the secrets in the CSV export | admin-guide.md |
| `admin-csv-import.png` | The Import from CSV (paste) box with rows pasted, before applying | admin-guide.md |
| `admin-csv-applied.png` | After Apply: pasted rows checked and applied, filtered to Selected | admin-guide.md |
| `admin-csv-provisioned.png` | After Provision: the tokens provisioned, with secrets revealed via Show | admin-guide.md |
| `admin-totp-import.png` | The banner offering to import accounts from twofactor_totp | README, admin-guide.md |
| `admin-totp-duplicates.png` | The banner for users registered with both apps: per-user occ commands and the opt-in removal button | admin-guide.md |
| `admin-totp-duplicates-confirm.png` | The confirmation dialog before the app removes the twofactor_totp registrations | admin-guide.md |
| `admin-totp-import-success.png` | The confirmation toast after a successful import, with the banner recommending to disable twofactor_totp | admin-guide.md |
| `login-ocra.png` | The OCRA challenge shown at the login prompt | ocra.md |
| `login-setup-chooser.png` | The provider chooser shown during enforced login setup | README |
| `login-setup.png` | OATH enrollment during login (TOTP default) with secret and QR | README |
| `login-setup-ocra.png` | OATH login enrollment with Advanced settings open on OCRA | README |
