<!--
  SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
  SPDX-License-Identifier: AGPL-3.0-or-later
-->

<!-- Project header -->
<p>
  <picture><source media="(prefers-color-scheme: dark)" srcset="https://cdn.jsdelivr.net/gh/ernolf/twofactor_oath@main/img/app.svg"><img src="https://cdn.jsdelivr.net/gh/ernolf/twofactor_oath@main/img/app-dark.svg" alt="Two-Factor OATH logo" width="128" height="128" align="left"></picture>
  <h3>Two-Factor OATH (TOTP / HOTP / OCRA)</h3>
  <p>Advanced OATH two-factor provider: TOTP, HOTP and OCRA</p>
  <p>
    <a href="https://api.reuse.software/info/github.com/ernolf/twofactor_oath"><img alt="REUSE status" src="https://api.reuse.software/badge/github.com/ernolf/twofactor_oath"></a>
    <a href="https://apps.nextcloud.com/apps/twofactor_oath"><img alt="Nextcloud App Store" src="https://img.shields.io/badge/Nextcloud-App_Store-0082c9?logo=nextcloud&logoColor=white"></a>
    <a href="https://github.com/ernolf/twofactor_oath/releases/latest"><img alt="Latest release" src="https://img.shields.io/github/v/release/ernolf/twofactor_oath?sort=semver&color=0082c9"></a>
    <a href="https://explore.transifex.com/nextcloud/nextcloud/"><img alt="Localization on Transifex" src="https://img.shields.io/badge/l10n-Transifex-0082c9"></a>
    <a href="https://github.com/ernolf/ncmake"><img alt="built with ncmake" src="https://cdn.jsdelivr.net/gh/ernolf/ncmake@main/img/ncmake-badge.svg"></a>
  </p>
</p>
<hr>
<br clear="left">

`twofactor_oath` is a second-factor provider for the Nextcloud login. It is built for everything the bundled `twofactor_totp` cannot do: hardware OATH tokens, configurable algorithm and length, predetermined (custom) secrets, challenge-response, admin enrollment for whole groups, and mass provisioning. The simple cases stay simple, the advanced cases become possible.

## Documentation

The documentation lives in the **[wiki](https://github.com/ernolf/twofactor_oath/wiki)**.

* The [previous single-page README](https://github.com/ernolf/twofactor_oath/blob/v0.5.0/README.md) stays available as it was before the move to the wiki.
