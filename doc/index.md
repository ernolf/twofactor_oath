<!--
  SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
  SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Documentation

Documentation for the `twofactor_oath` Nextcloud app. Start at the [README](../README.md) for an overview and quickstart; the pages below go into depth.

## Pages

| Page | Audience | Contents |
| --- | --- | --- |
| [Administration guide](admin-guide.md) | Admins | Default secret length, managed and excluded groups, bulk provisioning, CSV export and paste import, import from `twofactor_totp` |
| [OCRA](ocra.md) | Admins, advanced users | What OCRA is, suites, the challenge-response login flow, and the `tools/ocra_device` software token |
| [Security](security.md) | Admins, reviewers | Encryption at rest, forced password confirmation, strict RFC mode, anti-replay, HOTP resynchronisation |
| [Compatibility](compatibility.md) | Everyone | Authenticator apps, hardware tokens, and how OATH differs from FIDO2/WebAuthn |
| [Design and specification](design.md) | Developers, reviewers | Architecture, libraries, the self-contained OCRA implementation, data model, state machine |
| [Development](development.md) | Developers | Environment setup, quality gates, frontend build, deployment |

## Quick links

- Source and issues: <https://github.com/ernolf/twofactor_oath>
- OATH standards: <https://openauthentication.org> and <https://www.openauthentication.org/specifications.html>
- OTP library used: [spomky-labs/otphp](https://github.com/Spomky-Labs/otphp)
