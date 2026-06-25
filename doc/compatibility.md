<!--
  SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
  SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Compatibility

This page tracks which authenticators and hardware tokens work with which settings. It is meant to grow into a community-maintained matrix. A cell should be backed by a test or a citable source, never a guess. An honest `?` is more useful than a wrong `yes`.

## OATH is not FIDO

A frequent question is how this app relates to security keys. OATH and FIDO2/WebAuthn solve the second factor in fundamentally different ways:

| | OATH OTP (this app) | FIDO2 / WebAuthn |
| --- | --- | --- |
| Secret model | shared symmetric secret | public/private key pair |
| What the user does | reads a code and types it | touches a key, nothing to type |
| Phishing-resistant | no (a code can be relayed) | yes (the signature is bound to the origin) |
| Offline / air-gapped token | yes (a card or TAN device needs no connection) | the authenticator device must be present and reachable |
| Typical authenticators | mobile authenticator apps, OATH cards, TAN-style challenge-response devices, OATH-capable keys | FIDO2 security keys (for example YubiKey), platform authenticators (Windows Hello, Face ID, Touch ID), passkeys |
| Nextcloud app | `twofactor_oath` (this app), `twofactor_totp` (TOTP only) | `twofactor_webauthn` (second factor) |

Use FIDO2 where phishing resistance is the priority and the hardware and browsers support it. Use OATH where you need codes that work on any device, offline tokens, cards, or challenge-response generators, or where you provision tokens for many users centrally. The two can coexist as separate second factors. Note that the `twofactor_webauthn` app provides WebAuthn as a second factor; passwordless WebAuthn login is a separate Nextcloud core capability and is out of scope here.

Note on naming: devices such as Nitrokey Pro and Nitrokey Storage are marketed mainly as OpenPGP smartcards and password devices, and Nitrokey FIDO2 is a separate FIDO product. Some models also implement OATH HOTP/TOTP. Check the OATH capability of the specific model before assuming it belongs in the OATH matrix below.

## The safe baseline

TOTP, SHA-1, 6 digits, 30 seconds works with virtually every authenticator. Deviate only when you know the target supports it. The **Strict RFC compliance** switch keeps you within the relevant RFC, which is the safe choice for interoperability, especially with hardware.

## Software authenticator apps

The `Icon` column is whether the app shows the issuer icon embedded in the QR code (the `image=` parameter). `Trash` is whether a deleted entry is recoverable (goes to a recycle bin first). `Cloud backup` is whether the app can back entries up to a cloud (iCloud, Google, and so on). `Open source` is the licensing of the app itself. Cells are `?` until verified; please do not change a `?` from memory.

| App | Android | iOS | Open source | TOTP | HOTP | Algorithms | Digits | Period | Icon | Trash | Cloud backup | Notes |
| --- | :-: | :-: | :-: | :-: | :-: | --- | --- | --- | :-: | :-: | :-: | --- |
| FreeOTP / FreeOTP+ | yes | yes | yes | yes | yes | SHA-1/224/256/384/512, MD5 | 6 to 9 | 15 s to 10 m | yes | ? | ? | Official site <https://freeotp.github.io>. Free, open source, displays the issuer icon. Recommended. Per token you can require the device lock (for example Face ID) before the code is shown; such tokens are excluded from backup for security. Values verified by manual entry on iOS; whether periods outside the listed set pass via QR is untested. See the note below. |
| Aegis | yes | no | yes | yes | yes | ? | ? | ? | no | ? | ? | Android only. The most configurable app; an existing entry can be edited afterwards, for example converting TOTP to HOTP while keeping the secret (this app can do the same). Does not import the icon from the QR, but a custom icon can be set or a community icon pack covering most sites can be loaded. |
| ente Auth | yes | yes | yes | yes | yes | SHA-1/256/512 | free entry | free entry | no | yes | ? | Shows the current and the next TOTP code (the upcoming one small beside it); the next-code preview cannot be turned off. Offers a few alternative app icons. Imports from plain text, an encrypted ente export, 2FAS, Aegis, andOTP, Bitwarden, Google Authenticator, Proton Authenticator, Raivo OTP and LastPass; exports encrypted, as plain text, or as plain HTML. Can find and clean up duplicate codes and make automatic local backups. App lock that, if set without a biometric, cannot be recovered if forgotten (Face ID avoids that). Also offers a Steam token type (a Steam-specific TOTP variant, not a generic OATH type). Importing an HOTP entry with SHA-256 and 10 digits crashed to a black screen; the entry was correct and worked after reloading the app, so it functions but is not robust at the edges. |
| Google Authenticator | yes | yes | no | yes | yes | SHA-1 only | ? | various, including non-standard steps | no | ? | ? | No icons at all. Rejects a QR with unsupported parameters instead of silently misreading it. |
| Microsoft Authenticator | yes | yes | no | yes | ? | SHA-1 only | 6 | 30 s | no | ? | ? | Manual entry takes only the account name and secret; a QR with other digits, period or algorithm is forced to SHA-1 / 6 digits / 30 s. |
| 2FAS | yes | yes | yes | yes | yes | TOTP: MD5, SHA-1/224/256/384/512; HOTP: SHA-1 only | 5 to 8 | 10 / 30 / 60 / 90 s | no | yes | yes | Official site <https://2fas.com>. On iOS published as "2FA Authenticator (2FAS)" by Two Factor Authentication Service Inc. (many lookalikes exist). No icons at all, not even custom ones. The algorithm is selectable for TOTP only; HOTP is fixed to SHA-1. Period and digits are limited to the listed values. Cloud backup is iCloud sync (can be turned on or off). PIN or Face ID lock, browser add-ons, optional next-code preview, and tokens can be hidden so a code only shows on tap. Imports tokens from Aegis, Raivo OTP, LastPass, Google Authenticator, andOTP, Stratum (Authenticator Pro) and a generic OTPAuth file; exports tokens to a file or as a QR code. Also offers a Steam token type. |
| Yubico Authenticator | yes | yes | yes | ? | ? | ? | ? | ? | ? | ? | ? | Stores credentials on a YubiKey rather than in the app, so there is no app-side backup or sync. |

> [!NOTE]
> FreeOTP is free on both the Apple App Store and Android, open source, and it is the only authenticator that automatically renders the issuer icon this app embeds in the QR, which makes it the recommendation. On first use it asks for a permission; if that is not granted yet, the icon of the very first account can fail to load. Grant the permission, swipe the account away to delete it, then scan the QR code again.

> [!NOTE]
> Subscription-only authenticator apps are intentionally not listed. The free apps above are sufficient, so a recurring fee for a basic OTP app is hard to justify.

## Hardware tokens

Any hardware device that follows the OATH standard works with this app, whether it is a key fob, a display card, or a challenge-response generator, as long as it is provisioned in Strict RFC mode (so the parameters stay within the RFC the device implements). What a device offers beyond the baseline is what matters for a given deployment.

Programmable tokens take a seed you set. Provision them with a custom Base32 secret of the length the device expects (commonly a 20-byte / 160-bit SHA-1 seed, which is 32 Base32 characters and also this app's default).

### Manufacturers

A non-exhaustive list of vendors that sell OATH-certified or OATH-compliant hardware (HOTP/TOTP, some also OCRA, in key-fob, display-card and keypad form factors). Confirm the exact model before deploying; the offerings below follow the vendor pages.

| Vendor | OATH offering (examples) | Link |
| --- | --- | --- |
| FEITIAN | OTP c100 (HOTP), c200 (TOTP, NFC variant), c300 (OCRA, PIN keypad), VC display cards | <https://www.ftsafe.com/products/otp> |
| Thales (SafeNet) | SafeNet OTP 110 (OATH-certified), eToken PASS, OTP Display Card | <https://cpl.thalesgroup.com/access-management/authenticators/oath-tokens> |
| Protectimus | TWO (SHA-1), SHARK (SHA-256), Slim NFC (programmable card), FLEX (programmable fob) | <https://www.protectimus.com/tokens/> |
| HID Global | ActivID OTP tokens (key fob, display card, keypad with challenge-response) | <https://www.hidglobal.com/products/one-time-password-tokens> |
| Deepnet Security | SafeID OTP tokens, cards and keys | <https://www.deepnetsecurity.com/authenticators/one-time-password/> |
| Microcosm | c100/c200/c200m fobs, VC display cards, c300 (OCRA) | <https://microcosm.com/it-security-hardware/oath-otp-authentication-tokens> |

PIN-protected tokens (for example the FEITIAN c300 or the HID keypad tokens) gate the code display on the device itself; the server still verifies the resulting OTP normally, so no special server support is needed.

### Example device mapping (Microcosm)

A worked example of how device parameters map to the app. Provision these in Strict RFC mode.

| Device | Form factor | Type | Algorithm | Digits | Period | Re-seedable |
| --- | --- | --- | --- | :-: | :-: | --- |
| c100-HOTP | key fob | HOTP | SHA-1 | 6 | n/a | factory-set |
| c200-TOTP | key fob | TOTP | SHA-1 | 6 or 8 | 60 s | factory-set |
| c200 (programmable) | key fob | TOTP | SHA-1 | 6 | 30 / 60 s | yes (NFC) |
| c200m (programmable) | key fob | TOTP | SHA-1 / SHA-256 | 6 | 30 / 60 s | yes |
| VC-100E | display card | HOTP | SHA-1 | 6 | n/a | factory-set |
| VC-200E | display card | TOTP | SHA-1 | 6 | 60 s | factory-set |
| VC-N200E (programmable) | display card | TOTP | SHA-1 | 6 | 30 / 60 s | yes (NFC) |
| c300 | keypad (PIN) | TOTP and OCRA | SHA-1 | 6 or 8 | 60 s | factory-set |

### How complete should this list be?

This page is representative, not a catalogue. A full, always-current list of every OATH device would be unmaintainable and would read like advertising. For an exhaustive cross-vendor overview see Wikipedia's [comparison of OTP applications](https://en.wikipedia.org/wiki/Comparison_of_OTP_applications) (note that it mixes OATH with unrelated security technologies). The rule that matters: any device certified or documented as OATH-compliant works when provisioned in Strict RFC mode.

## How to contribute

This page is a starting point. To extend it, change a `?` to `yes` or `no` only for a parameter you have actually tested, or that a vendor or source documents, and add the evidence (app name and version with the date you tested, or a link to the spec) in the Notes column.
