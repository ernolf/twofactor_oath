<!--
  SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
  SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.1] - 2026-07-13

### Fixed

- Prevent the app from being limited to groups

### Added

- German and Brazilian Portuguese translations

[0.2.1]: https://github.com/ernolf/twofactor_oath/releases/tag/v0.2.1

## [0.2.0] - 2026-07-06

### Fixed

- Include pending tokens in the CSV secret export
- Adapt source strings from translation review

[0.2.0]: https://github.com/ernolf/twofactor_oath/releases/tag/v0.2.0

## [0.1.0] - 2026-06-25

### Added

- Initial release: an advanced OATH second-factor provider for Nextcloud.
- TOTP ([RFC 6238](https://www.rfc-editor.org/info/rfc6238/)), HOTP ([RFC 4226](https://www.rfc-editor.org/info/rfc4226/)) and OCRA ([RFC 6287](https://www.rfc-editor.org/info/rfc6287/)) tokens, with a self-contained OCRA implementation verified against the RFC 6287 test vectors.
- Per-token configuration: hash algorithm (SHA-1/224/256/384/512), digit count, period or counter, OCRA suite, and an optional predetermined secret.
- Secret length chosen by byte-strength presets; pasted custom Base32 secrets validated for a clean byte boundary.
- Secrets encrypted at rest with the Nextcloud instance key (`ICrypto`).
- Strict RFC compliance UI guard for interoperable defaults.
- QR code with a centered issuer icon (FreeOTP style) and a hardened reveal of an existing secret and QR (forced password confirmation, 60-second auto-hide).
- Admin management: bulk provisioning, CSV paste import and CSV export, admin-locked tokens, managed and excluded groups, disable selected tokens, invert and shift-range selection, and live status banners.
- Import of existing tokens from the bundled `twofactor_totp` app.
- HOTP resynchronisation with two consecutive codes, at the login prompt and in personal settings.
- Login setup and personal settings UI, plus a login challenge for TOTP, HOTP and OCRA.
- Admin deactivation via `occ twofactorauth:disable <uid> oath` (`IDeactivatableByAdmin`).
- Documentation (README, design, security, compatibility, admin guide, development) with screenshots, and internationalisation scaffolding (Transifex config, `l10n/`).

[0.1.0]: https://github.com/ernolf/twofactor_oath/releases/tag/v0.1.0
