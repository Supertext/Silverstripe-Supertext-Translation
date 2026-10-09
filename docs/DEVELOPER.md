# Developer guide — Supertext Translation for Silverstripe

How the module is built, how to work on it, and how it is released and deployed.

## Architecture

A Silverstripe 6 vendor module (`supertext/silverstripe-supertext-translation`, namespace `Supertext\Silverstripe`) on top of Fluent 8.

```
Page editor: "Supertext" tab (SupertextPageExtension, on SiteTree)          client/supertext.js
   │ POST admin/pages/edit/EditForm/<id>  action_doSupertextTranslate  (SupertextTargets[], SupertextOverwrite)
   ▼
SupertextCMSMainExtension::doSupertextTranslate  ── SUPERTEXT_TRANSLATE + canEdit, source = current CMS locale
   ▼
Service\Translator::translate(record, source, targets, overwrite, member)
   │ collect(): in the source locale (draft), the record's localised text fields + those of the
   │            localised objects it owns (findOwned, e.g. Elemental blocks), recursively
   │ per target: skip if it exists (unless overwrite); check SUPERTEXT_TRANSLATE and canEdit inside the target locale
   │ Api\HtmlDocument::build(): one <div data-st-id="N"> per value; chunks below 900k characters
   ▼
Api\SupertextClient   POST file → poll status → GET translation → DELETE   (Guzzle transport)
   ▼
apply(): in the target locale (draft): set the fields on each owned object and the record, writeToStage(DRAFT);
         new locale → URLSegment from the translated title; Model\TranslationLog rows
```

| Path | Responsibility |
| --- | --- |
| `src/Api/` | Supertext API client, HTML document, chunks. No Silverstripe classes (HTTP through a transport callable), unit-tested on their own |
| `src/Supertext.php` | Settings (YAML + `SUPERTEXT_API_KEY` / `SUPERTEXT_API_URL`), language code and tone per locale, the Guzzle client, the permission `SUPERTEXT_TRANSLATE` |
| `src/Service/Translator.php` | Field rules, collecting, translating, saving |
| `src/Extension/SupertextPageExtension.php` | The Supertext tab (fields are not saved into the record: `saveSupertext*()` no-ops) |
| `src/Extension/SupertextCMSMainExtension.php` | The form action on `CMSMain` |
| `src/Extension/LocaleExtension.php` | `SupertextCode`, `SupertextPoliteness` on Fluent's `Locale` |
| `src/Control/SupertextAdmin.php` | The *Supertext* CMS section: status, plugin version, *Test connection*, locales, log |
| `src/PluginVersion.php` | The installed version from `Composer\InstalledVersions` (no second copy in the code) and its GitHub release link for `X.Y.Z` / `vX.Y.Z`; no Silverstripe classes, unit-tested |
| `src/Model/TranslationLog.php` | Table `SupertextTranslation`: one row per record and target locale and run |
| `src/Task/` | `sake tasks:supertext-translate --page=<id> --from=en_US [--to=de_CH,fr_CH] [--overwrite] [--member=<email>]`, `sake tasks:supertext-check` |
| `client/` | Tab script (overwrite option, busy state, submitting buttons outside the toolbar) and styles, exposed to `_resources` |
| `lang/` | English, German, French and Italian strings (`en.yml` from `sake tasks:i18nTextCollectorTask --module=supertext/silverstripe-supertext-translation`; `de.yml`, `fr.yml`, `it.yml` by hand) |
| `src/Service/Messages.php` | Shows a `SupertextException` from the API client in the user's language (by its `reason`), falling back to the client's English message |

The CMS only submits forms through buttons in the bottom toolbar (`LeftAndMain.EditForm.js`); `client/supertext.js` triggers the submit for the tab's *Translate* and the admin's *Test connection* button the same way.

### Field rules

- A record is translated if it has `FluentExtension` (or `FluentVersionedExtension`). Its candidate fields are Fluent's localised fields (`getLocalisedTables()`), so Fluent's `translate`, `field_include`, `field_exclude`, `data_include` and `data_exclude` apply.
- Excluded: `Translator.exclude_fields` (`URLSegment`, `ExtraMeta`, `ReportClass`, `ExtraClass`, `Style`) and the class's `supertext_exclude_fields`.
- `HTMLText`, `HTMLVarchar` (and subclasses) are sent as HTML; everything else as escaped text with `<br>` for line breaks.
- **Owned objects** (`findOwned(false)`, recursively up to `max_depth`): included if they are localised **and** have content of their own in the source locale. This covers Elemental areas and blocks with direct localisation (Fluent on `BaseElement`).
- **URL segment**: for a locale that didn't exist, `SiteTree::generateURLSegment()` of the translated title; Silverstripe keeps it unique. Existing locales keep theirs.
- Empty values are skipped; an empty translation never overwrites.
- **Existing locale** = `isDraftedInLocale()` (versioned) or `existsInLocale()`. Fluent stores a row per locale only when content was saved there, so "exists" means "has its own content".
- Everything is written to the **draft** stage; nothing is published.

Each value is one `data-st-id` element, so a whole HTML field (all its paragraphs, bold words and links) is translated in context.

## Supertext API protocol

Shared with the WordPress plugin and every other Supertext CMS plugin:

1. `POST {base}translate/ai/file`: multipart with `file` (part `Content-Type` exactly `text/html`, no charset, or the API answers 415), `target_lang` (BCP-47, e.g. `de-CH`), optional `source_lang` (primary subtag only, e.g. `en`, or the pair is rejected), optional `politeness` (`more`/`less`). Returns `{file_id}`.
2. `GET …/{file_id}/status` until `done` (`error`, `limit_exceeded`, `deleted` are terminal).
3. `GET …/{file_id}/translation` returns the translated HTML.
4. `DELETE …/{file_id}` (files also expire after 24 h).

Auth header: `Authorization: Supertext-Auth-Key <key>`. The header name must be `Authorization` (`Authentication` gets 403). Supertext shows the key with the prefix, so the client strips a pasted `Supertext-Auth-Key ` and always sends exactly one. Base URLs: `https://api.supertext.com/v1/` (live), `https://api.staging.supertext.com/v1/`, `https://api.testing.supertext.com/v1/`. `GET features` is the cost-free key check (*Test connection*, `supertext-check`).

**Rate limit:** the API limits requests per second per key (HTTP 429). The client retries a 429 up to 4 times, waiting for `Retry-After` if sent, otherwise 1, 2, 4 and 8 seconds plus jitter. Target locales are translated one after the other.

## Local development

The demo project in `demo/project` installs the module from `demo/module` (a Composer path repository), which `demo/stage-module.sh` fills with the module's files. Silverstripe 6 needs MySQL or MariaDB (there is no PostgreSQL adapter for Silverstripe 6).

```bash
demo/stage-module.sh
cd demo/project
composer install
cat > .env <<'ENV'
SS_DATABASE_CLASS="MySQLDatabase"
SS_DATABASE_SERVER="127.0.0.1"
SS_DATABASE_USERNAME="…"
SS_DATABASE_PASSWORD="…"
SS_DATABASE_NAME="ss_demo"
SS_ENVIRONMENT_TYPE="dev"
SS_BASE_URL="http://127.0.0.1:8096"
SUPERTEXT_API_KEY="…"
ENV
vendor/bin/sake db:build --flush
DEMO_ADMIN_EMAIL=you@example.com DEMO_ADMIN_PASSWORD='…' vendor/bin/sake tasks:supertext-demo-setup
php -S 127.0.0.1:8096 -t public ../router.php   # serves files, everything else through public/index.php
# CMS: http://127.0.0.1:8096/admin
```

After changing the module, run `demo/stage-module.sh` and `composer update supertext/silverstripe-supertext-translation` (or copy the changed files into `vendor/supertext/silverstripe-supertext-translation`) and flush.

To work without a real key, run the stand-in API (`node tests/docs/stand-in.mjs`) and set `SUPERTEXT_API_URL=http://127.0.0.1:8765/v1/`. It returns real German, French and Italian for the demo's pages and `[de-CH] …`-prefixed text for anything else.

## Tests

```bash
composer install
vendor/bin/phpunit
```

- `tests/unit/SupertextClientTest.php`: the API protocol, auth header and prefix, 429 retries, errors, clean-up.
- `tests/unit/HtmlDocumentTest.php`, `tests/unit/ChunksTest.php`: document packing and parsing, whitespace, splitting below the size limit.
- `tests/unit/LangFilesTest.php`: `de.yml`, `fr.yml` and `it.yml` have every string of `en.yml` with the same placeholders, tags and URLs, and `en.yml` has every `_t(self::class . '.KEY')` used in `src/`.
- `tests/demo-check.sh` (CI): the demo image on MySQL with the stand-in, started twice: demo accounts created once and never duplicated, no passwords in the log, the Editors group and its permissions, the locales, `supertext-check`, translation of the sample page as the editor into three locales (page fields, ASCII URL segments, block titles and HTML with markup), the skip on a second run, and the log.

CI (`.github/workflows/ci.yml`) on every push and pull request: **test** (PHP lint, PHPUnit), **phpstan** (see *Code quality and security checks*) and **demo** (builds `demo/Dockerfile`, runs `tests/demo-check.sh`).

## Demo (Railway)

The public demo is a container built from `demo/Dockerfile`: PHP 8.3 with Apache, Silverstripe 6.2 with Fluent, Elemental and this module, locales English (`en_US`, default), Deutsch (`de_CH`), Français (`fr_CH`) and Italiano (`it_CH`) with formal tone, and a sample page with three content blocks. It runs on Railway in the `supertext-cms-demos` project, service `Silverstripe`, region EU West (Amsterdam): <https://silverstripe-production.up.railway.app/> (CMS: `/admin`). Data lives in a `silverstripe` database on the project's MySQL service.

**Deploys:** Railway builds `main` of this repository (`railway.json` points it at `demo/Dockerfile`).

**What's in `demo/`:**

| Path | Purpose |
| --- | --- |
| `Dockerfile` | PHP 8.3 + Apache (document root `demo/project/public`), PHP extensions, the module copied to `demo/module`, Composer install of `demo/project` |
| `docker/entrypoint.sh` | Every start: `DATABASE_URL` → `SS_DATABASE_*` (database `SILVERSTRIPE_DB_NAME`), `SS_BASE_URL` from `RAILWAY_PUBLIC_DOMAIN`, `.env` for later `sake` calls, `sake db:build --flush` (creates the database if missing), `sake tasks:supertext-demo-setup`, Apache on `$PORT` |
| `router.php` | Router for PHP's built-in server (local development) |
| `stage-module.sh` | Copies the module's files to `demo/module` for local installs |
| `project/` | The Silverstripe project: `composer.json`/`.lock`, `app/_config/demo.yml` (Elemental on pages, Fluent on blocks), `app/templates/Page.ss` (layout with locale switcher) and `app/src/DemoSetupTask.php` |
| `_manifest_exclude` | Keeps Silverstripe's class manifest out of `demo/` (the folder stays in Git archives because Railway builds from one) |
| `.env.example` | The variables below |

**Demo setup** (`sake tasks:supertext-demo-setup`, every start, only adds what is missing): the four locales (formal tone for German, French, Italian), the group **Editors**, the demo accounts, and the English pages *Welcome* and *Swiss chocolate, shipped worldwide* (the installer's *About Us* and *Contact Us* are archived).

**No volume:** Railway's volume limit for the project is reached, and the demo needs none: everything is in MySQL. Uploaded files would disappear with the next deploy.

**Service variables:**

| Variable | |
| --- | --- |
| `DATABASE_URL` | `${{MySQL.MYSQL_URL}}`; the demo uses the database `SILVERSTRIPE_DB_NAME` (default `silverstripe`) on that server |
| `DEMO_ADMIN_EMAIL`, `DEMO_ADMIN_PASSWORD` | Administrator (member of *Administrators*) |
| `DEMO_EDITOR_EMAIL`, `DEMO_EDITOR_PASSWORD` | Editor for automated tests and screenshots: member of **Editors** (*Access to Pages*, *Translate content with Supertext*, view draft content), no locale restrictions, so every locale. Silverstripe's default *Content Authors* group lacks the Supertext permission, so the demo creates this group. |
| `SUPERTEXT_API_KEY` | Supertext key |
| `SUPERTEXT_API_URL` | Optional, e.g. a stand-in API |
| `PORT` | Port Apache listens on (Railway sets it) |

Railway also sets `RAILWAY_PUBLIC_DOMAIN`; the entrypoint makes `SS_BASE_URL` from it and trusts the proxy (`SS_TRUSTED_PROXY_IPS=*`) for HTTPS.

**Demo accounts:** on every start the setup creates the `DEMO_ADMIN` and `DEMO_EDITOR` accounts if no member with that e-mail address exists. Existing accounts are never changed; change passwords in the CMS. Passwords must pass Silverstripe 6's password validator (entropy based: long, varied passwords); if one doesn't, that account is skipped with a warning naming the variable and Silverstripe's reason, and the demo still starts. Passwords are never logged. Silverstripe 6 has no web installer, so there is no first-run screen; without `DEMO_ADMIN_*` there is no administrator.

**Run it locally:**

```bash
docker build -f demo/Dockerfile -t supertext-silverstripe-demo .
docker run --rm -p 8080:8080 \
  -e DATABASE_URL=mysql://root:pass@host.docker.internal:3306/mysql \
  -e SS_BASE_URL=http://localhost:8080 \
  -e DEMO_ADMIN_EMAIL=you@example.com -e DEMO_ADMIN_PASSWORD='choose-a-long-one' \
  -e SUPERTEXT_API_KEY=… supertext-silverstripe-demo
# http://localhost:8080/admin
```

## Docs screenshots

The images in `docs/images/` are generated by `tests/docs/screenshots.mjs` (Playwright) from a freshly set-up demo (no translations yet) whose module talks to `tests/docs/stand-in.mjs`. The stand-in returns German, French and Italian for the demo's pages (`samples.json`, real Supertext output). Regenerate them whenever a screen they show changes:

```bash
cd tests/docs && npm install && npx playwright install chromium
npm run stand-in &
# a fresh demo (db:build + supertext-demo-setup with DEMO_* set), served on :8096 with
# SUPERTEXT_API_KEY=anything SUPERTEXT_API_URL=http://127.0.0.1:8765/v1/
BASE_URL=http://127.0.0.1:8096 DEMO_ADMIN_EMAIL=… DEMO_ADMIN_PASSWORD=… \
  DEMO_EDITOR_EMAIL=… DEMO_EDITOR_PASSWORD=… npm run screenshots
```

The script shows the live API address instead of the stand-in's and the public demo's address (`SITE_URL`) instead of the local one.

## Code quality and security checks

- **Checks** (`.github/workflows/checks.yml`): on every push and pull request, [actionlint](https://github.com/rhysd/actionlint) and [zizmor](https://docs.zizmor.sh) lint the workflows. On pull requests, dependency review fails a PR that adds a package with a known vulnerability (moderate or worse). Third-party actions are pinned to commit SHAs (Dependabot keeps them current); checkouts don't keep credentials, and workflows get `contents: read` unless a job needs more (the release job: `contents: write`).
- **Links** (`.github/workflows/links.yml`): [lychee](https://lychee.cli.rs) checks the links in all Markdown files weekly and whenever docs change on `main`. Broken links open or update the issue "Broken links in the docs" (a docs push that breaks links also fails). Links that can't work from CI go in `.lycheeignore` (one regex per line).
- **PHPStan** (job `phpstan` in `ci.yml`, config `phpstan.neon`): level 5 on the module's own code (`src/`), not the tests or `demo/`. [cambis/silverstan](https://github.com/cambis/silverstan) (a dev dependency) tells PHPStan about Silverstripe's configuration properties, extensions and injector. Locally: `composer install`, then `vendor/bin/phpstan analyse`. Existing findings that aren't simple to fix are listed in `phpstan-baseline.neon` (mostly Fluent's extension methods such as `existsInLocale()`, which Silverstripe adds at runtime; regenerate with `vendor/bin/phpstan analyse --generate-baseline` after fixing one). New code must not add findings.
- **GitHub settings** (set by Remy's setup script, not in the repo): secret scanning with push protection (a push containing a known token format is rejected; findings under *Security → Secret scanning*) and CodeQL default setup (findings under *Security → Code scanning* and as PR comments). CodeQL doesn't cover PHP, which is why this repo runs PHPStan.

Before starting work in this repo, look at its open findings: code scanning alerts, secret scanning alerts, Dependabot PRs and the "Broken links in the docs" issue.

## Releasing

Releases are published by `.github/workflows/release.yml` when the version is officially bumped; nobody tags or creates releases by hand.

1. Check that if strings changed, `lang/en.yml` is regenerated with the text collector and `lang/de.yml`, `fr.yml` and `it.yml` match.
2. Move the *Unreleased* entries in `CHANGELOG.md` under a new `## X.Y.Z — YYYY-MM-DD` section, and keep an empty *Unreleased* above it.
3. There is no version field to change: Composer takes the version from the Git tag the workflow creates.
4. Push to `main`. The workflow tags `vX.Y.Z` and creates the GitHub release with the CHANGELOG section as notes (0.x versions as pre-releases). A push that adds no new version does nothing, and a version that is already released is skipped. After fixing a failed run, start it again with *Run workflow* on the *Release* workflow.

Submitting the package to Packagist is planned.
## Conventions

- PSR-12, PHP 8.3, typed properties; keep `src/Api/` free of Silverstripe classes.
- User-visible strings through `_t()` with English, German, French and Italian in `lang/`; new or changed strings need all four in the same commit. Formal address (Sie, vous, Lei) and the CMS's own terms (*Seite/Entwurf*, *page/brouillon*, *pagina/bozza*).
- The API client (`src/Api/`, no Silverstripe classes) throws `SupertextException` with an English message and a `reason` (e.g. `limit_exceeded`); `Service\Messages::of()` maps it to a `_t()` string. A new reason needs a `match` arm there and its strings in all four files.
- Keep the three docs in `docs/` current with every change (see `CLAUDE.md`).

## Known limitations / roadmap

- Translation runs inside the editor's request (one locale after the other, up to `timeout` each). Planned: a queued job and a batch action for several pages.
- Pages (`SiteTree`) only; Fluent-localised DataObjects in ModelAdmin are not offered yet.
- Elemental with *indirect* localisation (a separate block area per locale) is not supported.
- Existing locales keep their URL segment when overwritten.
- Not on Packagist yet.
- Human (professional) translation orders are not supported yet (the WordPress plugin has them).
