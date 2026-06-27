# SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Makefile for Nextcloud app build and App Store management

app_name      = $(notdir $(CURDIR))
dist_dir      = $(CURDIR)/build/artifacts/dist
cache_dir     = $(CURDIR)/build/cache
apps_cache    = $(cache_dir)/apps.json
apps_etag     = $(cache_dir)/apps.etag
cert_dir      = $(HOME)/.nextcloud/certificates
# Make a missing cert dir obvious: cert_dir_note is appended wherever cert_dir is shown,
# require_cert_dir aborts the maintainer targets that need it with a clear message.
cert_dir_note = $(if $(wildcard $(cert_dir)),, [NOT FOUND])
require_cert_dir = @test -d "$(cert_dir)" || { echo "cert dir not found: $(cert_dir) - create it and add the App Store cert/key/token." >&2; exit 1; }
version       = $(shell xmllint --xpath 'string(//version)' appinfo/info.xml)
tarball       = $(dist_dir)/$(app_name)-$(version).tar.gz
appstore_api  = https://apps.nextcloud.com/api/v1
api_token     = $(shell cat $(cert_dir)/appstore_api-token 2>/dev/null | tr -d '[:space:]')
# Parse exclude list from krankerl.toml and generate --exclude flags for tar
exclude_flags = $(shell python3 -c 'c=open("krankerl.toml").read();s=c[c.index("[",c.index("exclude"))+1:c.index("]",c.index("exclude"))];items=[x.split(chr(34))[1] for x in s.split(chr(10)) if chr(34) in x];[print("--exclude=../$(app_name)/"+i) for i in items]')

# == Container runtime (used by 'make version' and 'make build') ==
# composer/npm run in throwaway containers, so the host needs no PHP/Node toolchain
# (mirrors befehle_twofactor_oath.txt and doc/development.md). The runtime is auto-detected
# (podman preferred, then docker); if neither is installed, the container targets abort with
# a hint to install podman. Override on the command line, e.g. 'make build RUNTIME=docker':
#   podman-rootless  rootless podman
#   docker           standard rootful docker (maps your uid to avoid root-owned files)
#   docker-rootless  rootless docker
#   bare             no container; composer and npm must be on PATH
have_podman := $(shell command -v podman 2>/dev/null)
have_docker := $(shell command -v docker 2>/dev/null)
ifneq ($(have_podman),)
  default_runtime := podman-rootless
else ifneq ($(have_docker),)
  default_runtime := docker
else
  default_runtime := none
endif
RUNTIME     ?= $(default_runtime)
no_runtime   = sh -c 'echo "ERROR: no container runtime found (podman/docker). Install podman: apt-get install podman, or use RUNTIME=bare to build on the host." >&2; exit 1'
# Image tags are derived from the declared support range, never hardcoded: the PHP CI
# image from info.xml's min-version, the Node image from package.json's engines.node.
php_min     = $(shell xmllint --xpath 'string(//dependencies/php/@min-version)' appinfo/info.xml 2>/dev/null)
node_major  = $(shell python3 -c 'import json,re;print(re.search(r"[0-9]+", json.load(open("package.json"))["engines"]["node"]).group())' 2>/dev/null)
php_image   ?= ghcr.io/nextcloud/continuous-integration-php$(php_min):latest
node_image  ?= node:$(node_major)

ifeq ($(RUNTIME),bare)
  php_run  = sh -lc
  node_run = sh -lc
else ifeq ($(RUNTIME),none)
  php_run  = $(no_runtime)
  node_run = $(no_runtime)
else ifeq ($(RUNTIME),podman-rootless)
  container = podman run --rm -v "$(CURDIR)":/app -w /app
else ifeq ($(RUNTIME),docker-rootless)
  container = docker run --rm -v "$(CURDIR)":/app -w /app
else ifeq ($(RUNTIME),docker)
  container = docker run --rm --user $(shell id -u):$(shell id -g) -v "$(CURDIR)":/app -w /app
else
  $(error Unknown RUNTIME '$(RUNTIME)'. Use: podman-rootless | docker | docker-rootless | bare)
endif

ifeq ($(filter $(RUNTIME),bare none),)
  php_run  = $(container) -e COMPOSER_HOME=/tmp/composer -e COMPOSER_ALLOW_SUPERUSER=1 $(php_image) sh -lc
  node_run = $(container) $(node_image) sh -lc
endif

.PHONY: all version tag build check-build dist sign release \
        fetch-apps \
        register publish list-releases list-releases-full list-for-author delete-release ratings \
        clean dist-clean help

# `make` with no target shows the help instead of building anything.
.DEFAULT_GOAL := help

all: dist

# == Release versioning (maintainer only) ==

# Open the next release (maintainer only - needs repo write access). Runs from main, explains
# itself, prompts + validates the version (> latest tag, empty = abort), then branches off into
# release/X.Y.Z and commits the bump there: info.xml/composer.json/package.json plus the
# re-synced lockfiles (via RUNTIME). 'main' is protected, so the bump lands through that branch
# and a PR, not a direct push. Fill the CHANGELOG on the branch; after the PR is merged, run
# 'make tag' on main.
version:
	@cur=$$(git rev-parse --abbrev-ref HEAD 2>/dev/null); \
	if [ "$$cur" != "main" ]; then echo "make version must run on 'main' (you are on '$$cur')." >&2; exit 1; fi; \
	echo "Maintainer target: opens a release branch with the version bump for a PR"; \
	echo "(main is protected, no direct push); after the merge you tag it with 'make tag'."; \
	latest=$$(git tag --list 'v*' --sort=-v:refname | head -1 | sed 's/^v//'); \
	latest=$${latest:-0.0.0}; \
	printf 'New version (latest tag: %s, empty = abort): ' "$$latest"; read new; \
	[ -z "$$new" ] && { echo "Aborted."; exit 0; }; \
	echo "$$new" | grep -qE '^[0-9]+\.[0-9]+\.[0-9]+$$' || { echo "Not a X.Y.Z version: $$new" >&2; exit 1; }; \
	high=$$(printf '%s\n%s\n' "$$latest" "$$new" | sort -V | tail -1); \
	if [ "$$new" = "$$latest" ] || [ "$$high" != "$$new" ]; then echo "Version $$new must be greater than the latest tag $$latest" >&2; exit 1; fi; \
	branch="release/$$new"; \
	git rev-parse --verify --quiet "refs/heads/$$branch" >/dev/null && { echo "Branch $$branch already exists - delete it or pick another version." >&2; exit 1; }; \
	git checkout -b "$$branch" || exit 1; \
	sed -i -E "s|<version>[0-9.]+</version>|<version>$$new</version>|" appinfo/info.xml; \
	[ -f composer.json ] && sed -i -E "s|(\"version\"[[:space:]]*:[[:space:]]*)\"[0-9.]+\"|\1\"$$new\"|" composer.json || true; \
	[ -f package.json ] && sed -i -E "s|(\"version\"[[:space:]]*:[[:space:]]*)\"[0-9.]+\"|\1\"$$new\"|" package.json || true; \
	if [ -f composer.json ] && [ -f composer.lock ]; then echo "==> Syncing composer.lock ($(RUNTIME))..."; $(php_run) 'composer update --lock' || { echo "composer.lock sync failed" >&2; exit 1; }; fi; \
	if [ -f package.json ] && [ -f package-lock.json ]; then echo "==> Syncing package-lock.json ($(RUNTIME))..."; $(node_run) 'npm install --package-lock-only' || { echo "package-lock.json sync failed" >&2; exit 1; }; fi; \
	git add appinfo/info.xml; \
	[ -f composer.json ] && git add composer.json; [ -f composer.lock ] && git add composer.lock; \
	[ -f package.json ] && git add package.json; [ -f package-lock.json ] && git add package-lock.json; \
	git commit -s -m "build(release): bump version to $$new"; \
	echo; \
	echo "==> Bumped to $$new on branch $$branch and committed."; \
	echo "==> Next: add a '## [$$new]' section to CHANGELOG.md, commit it on this branch,"; \
	echo "    push the branch, open a PR and merge. Then on main: git pull && make tag"

# Freeze the current HEAD as a signed release tag. Everything that belongs in the
# release must already be committed (CHANGELOG and all content). Refuses to re-tag.
tag:
	@ver=$$(xmllint --xpath 'string(//version)' appinfo/info.xml); \
	latest=$$(git tag --list 'v*' --sort=-v:refname | head -1 | sed 's/^v//'); \
	latest=$${latest:-0.0.0}; \
	high=$$(printf '%s\n%s\n' "$$latest" "$$ver" | sort -V | tail -1); \
	if [ "$$ver" = "$$latest" ] || [ "$$high" != "$$ver" ]; then echo "info.xml version $$ver is not greater than the latest tag $$latest - run 'make version' first" >&2; exit 1; fi; \
	echo "############################################################################"; \
	echo "##  About to create the signed tag v$$ver at the CURRENT commit."; \
	echo "##  EVERYTHING that belongs in this release MUST be committed NOW:"; \
	echo "##  CHANGELOG.md (## [$$ver]) and all release content. A tag is frozen."; \
	echo "############################################################################"; \
	if [ -f CHANGELOG.md ] && ! grep -q "^## \[$$ver\]" CHANGELOG.md; then \
		echo "WARNING: no '## [$$ver]' section found in CHANGELOG.md!" >&2; \
	fi; \
	printf 'Create and push the signed tag v%s now? [y/N] ' "$$ver"; read yn; \
	if [ "$$yn" = y ] || [ "$$yn" = Y ]; then \
		git tag -s "v$$ver" -m "Release $$ver" && git push origin "v$$ver"; \
	else \
		echo "Aborted. To do it manually:"; \
		echo "  git tag -s v$$ver -m \"Release $$ver\" && git push origin v$$ver"; \
	fi

# == Build ==

# Run the per-app build commands (before_cmds in krankerl.toml: composer, npm, etc.),
# each routed to its container via RUNTIME (see top of file) so the host needs no toolchain.
build:
	@echo "==> Building via RUNTIME=$(RUNTIME)"
	@python3 -c 'c=open("krankerl.toml").read();s=c[c.index("[",c.index("before_cmds"))+1:c.index("]",c.index("before_cmds"))];[print(x.split(chr(34))[1]) for x in s.split(chr(10)) if chr(34) in x]' \
		| while read -r cmd; do \
			echo "+ $$cmd"; \
			case "$$cmd" in \
				composer*) $(php_run) "$$cmd" ;; \
				npm*)      $(node_run) "$$cmd" ;; \
				*)         sh -lc "$$cmd" ;; \
			esac || exit 1; \
		done

# Abort (do not build a broken tarball) when the build outputs are missing
check-build:
	@if [ -f package.json ] && { [ ! -d js ] || [ -z "$$(ls -A js 2>/dev/null)" ]; }; then \
		echo "ERROR: js/ is missing or empty - run 'make build' first." >&2; exit 1; \
	fi
	@if grep -q '"require"' composer.json 2>/dev/null && [ ! -f vendor/autoload.php ]; then \
		echo "ERROR: vendor/autoload.php is missing - run 'make build' first." >&2; exit 1; \
	fi

# Build the distribution tarball (warns via check-build if js/ or vendor/ look unbuilt)
dist: check-build appinfo/info.xml
	rm -rf $(dist_dir)
	mkdir -p $(dist_dir)
	tar czf $(tarball) \
		--exclude-vcs \
		$(exclude_flags) \
		../$(app_name)
	@echo "Built: $(tarball)"

# Sign the tarball — output is the base64 signature to paste into GitHub Release
sign: $(tarball)
	$(require_cert_dir)
	@echo "Signing $(tarball)..."
	openssl dgst -sha512 -sign $(cert_dir)/$(app_name).key $(tarball) | openssl base64

# Build tarball and sign in one step
release: dist sign

# == App Store cache ==

# Fetch apps.json with ETag caching (always runs as prerequisite).
# 304 Not Modified → use cached file.
# 200 OK           → update cache and save new ETag.
# Error + cache    → warn and use stale cache.
# Error, no cache  → fail.
fetch-apps:
	@mkdir -p "$(cache_dir)"; \
	_etag=""; \
	test -f "$(apps_etag)" && _etag=$$(cat "$(apps_etag)"); \
	if [ -n "$$_etag" ] && [ -f "$(apps_cache)" ]; then \
		_http=$$(curl -sL --compressed -D /tmp/.fsr_hdrs -o /tmp/.fsr_apps_new -w "%{http_code}" \
			-H "If-None-Match: $$_etag" "$(appstore_api)/apps.json"); \
	else \
		_http=$$(curl -sL --compressed -D /tmp/.fsr_hdrs -o /tmp/.fsr_apps_new -w "%{http_code}" \
			"$(appstore_api)/apps.json"); \
	fi; \
	case "$$_http" in \
		304) rm -f /tmp/.fsr_apps_new; \
			echo "(apps.json not modified — using cache)";; \
		200) mv /tmp/.fsr_apps_new "$(apps_cache)"; \
			_new_etag=$$(grep -i '^etag:' /tmp/.fsr_hdrs | head -1 \
				| sed 's/^[Ee][Tt][Aa][Gg]:[[:space:]]*//' | tr -d '\r\n'); \
			[ -n "$$_new_etag" ] && printf '%s' "$$_new_etag" > "$(apps_etag)"; \
			echo "(apps.json updated)";; \
		*)  rm -f /tmp/.fsr_apps_new; \
			if [ -f "$(apps_cache)" ]; then \
				echo "(apps.json fetch failed HTTP $$_http — using stale cache)"; \
			else \
				echo "Failed to fetch apps.json (HTTP $$_http)."; exit 1; \
			fi;; \
	esac

# == App Store ==

# Register the app on the App Store (one-time setup).
# Requires: $(cert_dir)/$(app_name).cert  $(cert_dir)/$(app_name).key
register:
	$(require_cert_dir)
	@set -e; \
	test -f "$(cert_dir)/$(app_name).cert" || { echo "Certificate not found: $(cert_dir)/$(app_name).cert"; exit 1; }; \
	test -f "$(cert_dir)/$(app_name).key"  || { echo "Key not found: $(cert_dir)/$(app_name).key"; exit 1; }; \
	echo "Computing signature over app id '$(app_name)'..."; \
	echo -n "$(app_name)" | openssl dgst -sha512 -sign "$(cert_dir)/$(app_name).key" | openssl base64 | tr -d '\n' > /tmp/.fsr_sig; \
	python3 -c "import json;cert=open('$(cert_dir)/$(app_name).cert').read().strip().replace('\n','\r\n');sig=open('/tmp/.fsr_sig').read();print(json.dumps({'certificate':cert,'signature':sig}))" > /tmp/.fsr_body; \
	echo "Registering $(app_name) on the App Store..."; \
	http=$$(curl -s -o /tmp/.fsr_resp -w "%{http_code}" \
		-X POST \
		-H "Authorization: Token $(api_token)" \
		-H "Content-Type: application/json" \
		--data-binary @/tmp/.fsr_body \
		"$(appstore_api)/apps"); \
	case "$$http" in \
		201) echo "Success — app registered.";; \
		204) echo "Success — registration updated (certificate changed).";; \
		400) echo "HTTP 400 — invalid data or signature:"; cat /tmp/.fsr_resp; echo; exit 1;; \
		401) echo "HTTP 401 — check $(cert_dir)/appstore_api-token"; exit 1;; \
		403) echo "HTTP 403 — not authorized."; exit 1;; \
		*)   echo "HTTP $$http:"; cat /tmp/.fsr_resp; echo; exit 1;; \
	esac

# Publish a new release to the App Store.
# Run 'make dist' first, upload the tarball to GitHub, then run this.
# Prompts for the GitHub release download URL.
publish:
	$(require_cert_dir)
	@test -f "$(tarball)" || { echo "ERROR: $(tarball) not found — run 'make dist' first."; exit 1; }
	@read -p "GitHub release download URL (https://...): " url; \
	test -n "$$url" || { echo "Aborted."; exit 0; }; \
	echo "Computing signature..."; \
	openssl dgst -sha512 -sign "$(cert_dir)/$(app_name).key" "$(tarball)" | openssl base64 | tr -d '\n' > /tmp/.fsr_sig; \
	python3 -c "import sys,json;sig=open('/tmp/.fsr_sig').read();print(json.dumps({'download':sys.argv[1],'signature':sig}))" "$$url" > /tmp/.fsr_body; \
	echo "Publishing v$(version) to the App Store..."; \
	http=$$(curl -s -o /tmp/.fsr_resp -w "%{http_code}" \
		-X POST \
		-H "Authorization: Token $(api_token)" \
		-H "Content-Type: application/json" \
		--data-binary @/tmp/.fsr_body \
		"$(appstore_api)/apps/releases"); \
	case "$$http" in \
		200) echo "Release v$(version) updated on the App Store.";; \
		201) echo "Release v$(version) published successfully!";; \
		400) echo "HTTP 400 — invalid data, signature or URL not reachable:"; cat /tmp/.fsr_resp; echo; exit 1;; \
		401) echo "HTTP 401 — check $(cert_dir)/appstore_api-token"; exit 1;; \
		403) echo "HTTP 403 — not authorized."; exit 1;; \
		*)   echo "HTTP $$http:"; cat /tmp/.fsr_resp; echo; exit 1;; \
	esac

# List published releases of this app (compact JSON)
list-releases: fetch-apps
	@python3 -c "import sys,json;apps=json.load(open('$(apps_cache)'));app=next((a for a in apps if a['id']=='$(app_name)'),None);sys.exit(1) if not app else print(json.dumps({'id':app['id'],'releases':[{'version':r['version'],'created':r['created'],'download':r['download']} for r in app['releases']]},indent=2))" 2>/dev/null \
	|| echo "($(app_name) not found in App Store)"

# Full App Store entry as JSON
list-releases-full: fetch-apps
	@python3 -c "import sys,json;apps=json.load(open('$(apps_cache)'));app=next((a for a in apps if a['id']=='$(app_name)'),None);sys.exit(1) if not app else print(json.dumps(app,indent=2))" 2>/dev/null \
	|| echo "($(app_name) not found in App Store)"

# Find all apps by author name (prompts for search string)
list-for-author: fetch-apps
	@read -p "Author search string: " term; \
	test -n "$$term" || { echo "Aborted."; exit 1; }; \
	python3 -c "import sys,json;apps=json.load(open('$(apps_cache)'));term=sys.argv[1].lower();matched=[{'id':a['id'],'name':next(iter(a.get('translations',{}).values()),{}).get('name',''),'authors':a.get('authors',[]),'releases':[r['version'] for r in a['releases']]} for a in apps if any(term in au['name'].lower() for au in a.get('authors',[]))];print(json.dumps(matched,indent=2))" "$$term" 2>/dev/null \
	|| echo "Failed to search app list."

# Delete a specific release from the App Store (interactive)
delete-release: fetch-apps
	$(require_cert_dir)
	@set -e; \
	releases=$$(python3 -c "import sys,json;apps=json.load(open('$(apps_cache)'));app=next((a for a in apps if a['id']=='$(app_name)'),None);[print(r['version']) for r in (app or {}).get('releases',[])]" 2>/dev/null || true); \
	if [ -n "$$releases" ]; then \
		echo "Published releases:"; \
		echo "$$releases" | sed 's/^/  /'; \
	else \
		echo "(Could not read app data — current version in info.xml: $(version))"; \
	fi; \
	read -p "Version to delete (empty = abort): " ver; \
	test -n "$$ver" || { echo "Aborted."; exit 0; }; \
	read -p "Delete $(app_name) v$$ver from the App Store? [y/N] " confirm; \
	[ "$$confirm" = "y" ] || [ "$$confirm" = "Y" ] || { echo "Aborted."; exit 0; }; \
	http=$$(curl -s -o /dev/null -w "%{http_code}" \
		-X DELETE \
		-H "Authorization: Token $(api_token)" \
		"$(appstore_api)/apps/$(app_name)/releases/$$ver"); \
	case "$$http" in \
		204) echo "Release $$ver deleted successfully.";; \
		401) echo "HTTP 401 — check $(cert_dir)/appstore_api-token"; exit 1;; \
		403) echo "HTTP 403 — not authorized."; exit 1;; \
		404) echo "HTTP 404 — release $$ver not found."; exit 1;; \
		*)   echo "HTTP $$http — unexpected error."; exit 1;; \
	esac

# Show ratings for this app from the App Store
ratings:
	@curl -sf "$(appstore_api)/ratings.json" 2>/dev/null \
	| python3 -c "import sys,json;d=json.load(sys.stdin);own=[r for r in d if r.get('app')=='$(app_name)'];avg=round(sum(r['rating'] for r in own)/len(own)*5,2) if own else None;print(json.dumps({'app':'$(app_name)','count':len(own),'avgRating':avg,'ratings':[{'rating':round(r['rating']*5,1),'ratedAt':r['ratedAt'],'comment':next(iter(r.get('translations',{}).values()),{}).get('comment','')} for r in sorted(own,key=lambda r:r['ratedAt'],reverse=True)]},indent=2))" 2>/dev/null \
	|| echo "Failed to fetch ratings."

# == Utility ==

# Remove all build artifacts (including cache)
clean:
	rm -rf build

# Remove every git-ignored build output for a true from-scratch rebuild: runs 'clean' first
# (build/), then vendor/, node_modules/, js/, caches and the nested dependency repos composer
# leaves behind, via 'git clean -dffX' (-X = ignored files only, so untracked source stays;
# the second -f also clears those nested git repos). git clean lists each removed path.
dist-clean: clean
	git clean -dffX

# Show available targets and required files
help:
	@echo "Usage: make <target>    (no target = this help)"
	@echo ""
	@echo "Release versioning (maintainer only):"
	@echo "  version              Open a release branch with the version bump for a PR (prompts)  [m]"
	@echo "  tag                  Tag the release commit on main, signed, and push it  [m]"
	@echo ""
	@echo "  Container runtime for 'version' (lockfile sync) and 'build': RUNTIME=... (now: $(RUNTIME))"
	@echo "    podman-rootless | docker | docker-rootless | bare   (auto-detected, podman preferred)"
	@echo ""
	@echo "Build:"
	@echo "  build                Build frontend + PHP deps (composer/npm from krankerl.toml)"
	@echo "  dist                 Build the distribution tarball (run 'make build' first)"
	@echo "                       → $(tarball)"
	@echo "  sign                 Sign the tarball (base64 signature for publish / App Store)  [m]"
	@echo "  release              dist + sign in one step  [m]"
	@echo ""
	@echo "App Store  (cert dir: $(cert_dir)$(cert_dir_note))"
	@echo "           token: $(cert_dir)/appstore_api-token"
	@echo "           cert:  $(cert_dir)/$(app_name).cert"
	@echo "           key:   $(cert_dir)/$(app_name).key"
	@echo ""
	@echo "  register             Register app on the App Store (one-time).  [m]"
	@echo "                       Needs .cert and .key."
	@echo "  publish              Publish a new release.  [m]"
	@echo "                       Needs: tarball from 'make dist'."
	@echo "                       Prompts for: GitHub release download URL."
	@echo "  list-releases        List published releases (compact JSON)."
	@echo "  list-releases-full   Full App Store entry as JSON."
	@echo "  list-for-author      Find all apps by author (prompts for name)."
	@echo "  delete-release       Delete a release (shows list, prompts for version).  [m]"
	@echo "  ratings              Show app ratings from the App Store."
	@echo ""
	@echo "  apps.json cache: $(apps_cache)"
	@echo "           (ETag: $(apps_etag))"
	@echo ""
	@echo "Utility:"
	@echo "  clean                Remove build/ directory (incl. cache)"
	@echo "  dist-clean           Remove ALL git-ignored build outputs (vendor, node_modules, js, …)"
	@echo "  help                 Show this help"
	@echo ""
	@echo "  [m] = maintainer only (needs repo write access and/or the signing key/cert)"
	@echo ""
	@echo "Current: $(app_name) v$(version)"

