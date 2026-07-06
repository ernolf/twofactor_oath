<!--
  SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
  SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Development

How to set up the development environment and run the quality gates for `twofactor_oath`. There are two ways to work: directly on the host (native), or in throwaway containers (recommended, see [Container-based development](#container-based-development)).

## Requirements

For the container-based workflow (recommended), only **podman** or **Docker** is required — the toolchain below runs inside the images. For native, on-host work you need:

- PHP 8.1 or newer (the app supports Nextcloud 32 to 35, PHP 8.1 to 8.5)
- Composer
- Node.js and npm (for the frontend build)
- For the Nextcloud-dependent unit tests: a Nextcloud checkout (the app must live inside its `apps/` directory)

## Native setup

```sh
composer install      # first time
composer update       # after changing composer.json
```

This installs the runtime dependency (`spomky-labs/otphp`) into `vendor/` and, via the `bamarni/composer-bin-plugin`, all dev tools into separate `vendor-bin/<tool>/` namespaces (php-cs-fixer, psalm, phpunit, rector, nextcloud/ocp). Their binaries are linked into `vendor/bin/`, so the composer scripts below can call them directly.

## Quality gates

Run these before every commit. Everything except the Nextcloud-dependent tests runs standalone.

| Command | Purpose |
| --- | --- |
| `composer lint` | `php -l` syntax check over all PHP files |
| `composer cs:check` | code-style check (nextcloud/coding-standard) |
| `composer cs:fix` | auto-fix code-style issues |
| `composer psalm` | static analysis (errorLevel 4) |
| `vendor/bin/rector --dry-run` | show modernization suggestions (no changes) |
| `composer rector` | apply rector suggestions, then `cs:fix` |
| `composer test:unit` | PHPUnit unit tests |

Expected on a clean tree: `lint` clean, `cs:check` reports `0 of N files`, `psalm` says `No errors found` (info-level notes such as `MissingClassConstType` are expected, since typed class constants require PHP 8.3 and the app still supports 8.1), `rector --dry-run` reports no changes.

## Frontend (npm)

The Vue UI is built and checked with npm, following the Nextcloud convention:

```sh
npm ci                 # install exact versions from package-lock.json
npm run build          # production build into js/  (git-ignored)
npm run dev            # development build
npm run watch          # rebuild on change
npm run lint           # eslint        (npm run lint:fix to auto-fix)
npm run stylelint      # stylelint
npm run test           # vitest unit tests (npm run test:watch to watch)
```

## Container-based development

Run every gate in a throwaway container, so the host stays untouched and you can test against any PHP version (for example 8.1) even if it is not installed on the host. This mirrors the Nextcloud CI, which ships ready-made images per PHP version with all required extensions.

The `Makefile` automates exactly these invocations: `make build` and `make version` run the package managers in a container chosen by `RUNTIME` (see [Makefile targets](#makefile-targets)).

### Choosing a runtime

`docker` and `podman` take the same command syntax, so every example below works with either (`alias docker=podman`, or install the `podman-docker` shim).

| Runtime | Idle cost | Privileges | Best for |
| --- | --- | --- | --- |
| Podman (rootless) | none (daemonless) | rootless | minimal host intervention, recommended on a shared or low-RAM host |
| Docker (standard) | a daemon (~100 to 150 MB RAM) | root daemon | the common default, CI parity, a dedicated dev VM |
| Docker (rootless) | a per-user daemon | rootless | Docker tooling without a root daemon |
| Dev Containers | uses Docker/Podman underneath | n/a | reproducible VS Code environment |
| CI (GitHub Actions) | ephemeral cloud runners | n/a | the full PHP-version matrix on every push |

### Running the PHP gates in a container

The Nextcloud CI images (`ghcr.io/nextcloud/continuous-integration-php8.1`, `-php8.2`, `-php8.3`) bundle the matching PHP version, all required extensions and composer. They run as root internally, so composer needs `COMPOSER_ALLOW_SUPERUSER=1` to load the bamarni plugin.

```sh
for v in 8.1 8.2 8.3; do
  echo "== PHP $v =="
  podman run --rm -v "$PWD":/app -w /app \
    -e COMPOSER_HOME=/tmp/composer -e COMPOSER_ALLOW_SUPERUSER=1 \
    ghcr.io/nextcloud/continuous-integration-php${v}:latest \
    sh -lc 'composer install --no-interaction -q && composer lint && composer cs:check' \
    || break
done
```

Notes on the images:

- The `:latest` tags are pinned to fixed patch levels that can be older than the newest psalm requires (psalm 6.16 needs PHP 8.1.31 or newer). `lint`, `cs:check` and `phpunit` run fine on them; `psalm` does not on a stale patch. Run `psalm` once on a current PHP (simplest on the host).
- `psalm` analyses for the version configured in `psalm.xml` (`phpVersion="8.1"`) regardless of the runtime PHP.

### Running npm in a container

```sh
podman run --rm -v "$PWD":/app -w /app node:22 sh -lc 'npm ci && npm run build'
```

### Caveats

- File ownership: Podman rootless maps your user. With standard (rootful) Docker, add `--user "$(id -u):$(id -g)"` to avoid root-owned files.
- SELinux (Fedora/RHEL): append `:z` or `:Z` to the bind mount.

## Unit tests

`tests/bootstrap.php` boots the surrounding Nextcloud when the app sits inside a real `apps/` directory, and falls back to the app's own Composer autoloader otherwise. The OCRA test (`tests/unit/OcraTest.php`) is pure PHP and runs in either case:

```sh
vendor/bin/phpunit tests/unit/OcraTest.php
```

Tests that touch OCP/OC need a surrounding Nextcloud. For a checkout kept elsewhere, symlink it in:

```sh
ln -s /path/to/twofactor_oath /var/www/nextcloud/apps/twofactor_oath
composer test:unit
```

The same symlink lets Nextcloud load and enable the app for manual testing.

## Makefile targets

The `Makefile` is the [ncmake](https://github.com/ernolf/ncmake) bootstrap stub: on first use it fetches the shared ncmake Makefile into a per-machine cache (`~/.cache/ncmake/`) and keeps it current from then on. Building, packaging, deploying, releasing and the container runtime (`RUNTIME=`) are documented in the [ncmake README](https://github.com/ernolf/ncmake#readme); `make help` is the authoritative target list.

### Releasing

`main` is protected (required checks and signed commits), so a version bump cannot be pushed to it directly. The release cycle (`make version` on a `ncmake/release/X.Y.Z` branch, CHANGELOG entry, PR, then `make tag` on `main`) is described in the [ncmake README](https://github.com/ernolf/ncmake#releasing).

App-specific: from the tag you cut a GitHub release; the release workflow builds the tarball and attaches it as an asset. `make register` registers the app and its signing certificate (one-time); `make publish` then submits the release for publication on the App Store.

## Deploying to a test instance

For a quick iteration loop, sync the runtime files straight into an instance:

```sh
make build && make rsync TARGET=/path/to/nextcloud/apps/
chown -R www-data:www-data /path/to/nextcloud/apps/twofactor_oath
occ app:enable twofactor_oath
```

When updating an existing install on the same host, run:

```sh
make build && occ app:disable twofactor_oath && make rsync TARGET=/path/to/nextcloud/apps/ && chown -R www-data:www-data /path/to/nextcloud/apps/twofactor_oath && occ app:enable twofactor_oath
```

### Schema changes

A new or altered column requires disabling the app, dropping the table and the `oc_migrations` row for `twofactor_oath`, then re-enabling so the migration runs again. There is no automatic down-migration.

## The OCRA test token

`tools/ocra_device` is a standalone software OCRA token used to test challenge-response without hardware. It reuses the app's own (RFC-vector-verified) `Ocra` class. See [ocra.md](ocra.md).

## Licensing / REUSE

Every file carries an SPDX header (or is covered by `REUSE.toml`). Fetch the license text once so `reuse lint` passes:

```sh
reuse download AGPL-3.0-or-later   # creates LICENSES/AGPL-3.0-or-later.txt
reuse lint
```

## Notes

- The package version lives in `appinfo/info.xml`. `composer.json` mirrors it in `version` only to silence composer's version notice; once the repository is tagged (`vX.Y.Z`), composer infers it from the tag. Keep both in sync.
- The built frontend (`js/`) and `vendor/` are git-ignored; run `composer install` and the npm build after cloning.

