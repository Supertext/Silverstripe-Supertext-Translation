# Changelog

All notable changes to this project are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/).

## Unreleased

### Added

- French and Italian interface, and German where it was missing: messages from Supertext (limit reached, authentication failed, timeouts and others) now appear in the user's interface language too. The "no API key" and "authentication failed" messages now also link to Supertext account sign-up.
- The **Supertext** CMS section shows the installed plugin version under *Connection*, linked to its GitHub release notes for released versions.
- Automatic API key check: when the key in `SUPERTEXT_API_KEY` is new or changed, on *Test connection*, in `supertext-check` and when Supertext rejects the key during a translation. The Supertext section shows the latest result with its time (*the connection works*, *rejected*, *no key*, *Supertext can't be reached*, *error*, with the account and API key links where they help) in English, German, French and Italian. *Test connection* now shows that result.
- *Send version information to Supertext* (on by default; `version_reports: false` in YAML or `SUPERTEXT_VERSION_REPORTS=0` turns it off): after an update and when the key check result changes, the module sends the site URL, the module, Silverstripe CMS, PHP and SDK versions, whether a key is set, the key check result and the ping address to Supertext. No content, no API key and no personal data. Shown in the Supertext section; `supertext-check` sends a `check` report.
- Public ping endpoint `/supertext/ping` that only echoes Supertext's challenge, so Supertext can check the site is reachable (404 with version reports off).
- New table `SupertextState` for this state (run `sake db:build` after updating).

### Changed

- Version reports, the API key check result and the reachability ping now use Supertext Plugin Health 0.1.0 (bundled in `lib/supertext-plugin-health`); Supertext SDK updated to 0.3.0. No change for sites: settings and stored state stay as they are.
- **Languages are no longer guessed.** A locale that is a Supertext code (`de_CH`, `fr_CH`, `en_US`, `nl`, apart from case and `_`/`-`) is sent as that code; every other locale needs its *Supertext language* (Locales admin), which is now a list of Supertext's languages instead of a text field. The default regions are gone: they were wrong for most languages (Supertext wants `es`, `sl`, `nl` without region, `zh-Hans` instead of `zh-CN`). A locale without a Supertext language gets the message *has no Supertext language yet* (four languages) and nothing is sent for it; the Supertext section marks it *Not mapped*, the Supertext tab greys it out. The *Supertext language* serves as target and (without region) as source.
- Formal/informal for a language Supertext offers no tone for (English, Swiss German, Romansh, Albanian, Serbian) is flagged on the locale and in the Supertext section, and translating into it fails with *Supertext offers no formal or informal tone for this language* instead of sending it.
- All Supertext API calls now go through the official Supertext PHP SDK (0.2.1), bundled with the module (nothing extra to install).
- An unsupported language pair now has its own message, naming the codes ("Supertext does not support this language pair (en → xx-YY)"), in English, German, French and Italian.

## 0.1.0 — 2026-10-07

### Added

- First version for Silverstripe CMS 6 with Fluent 8.
- **Supertext** tab on pages: translate the current locale's saved draft into other locales, with "Already translated" / "Translated with Supertext on …" per locale and an explicit *Overwrite existing translations* option.
- Translates every text field Fluent localises (HTML as HTML) and those of the localised objects a page owns, such as Elemental blocks; new locales get a URL segment from the translated title. Translations are saved as drafts.
- Permission *Translate content with Supertext*; the user must be able to edit the page in each target locale (Fluent locale permissions apply).
- *Supertext language* and *Form of address* per locale in Fluent's Locales admin.
- **Supertext** CMS section: API key status, API address, *Test connection* (administrators), the locales and the translation log.
- Links to create a Supertext account and to generate the API key (supertext.com → Integrations → API, Admin role) in the **Supertext** section, in `supertext-check` when no key is set, and in the installation guide.
- Settings: `SUPERTEXT_API_KEY` (with or without the `Supertext-Auth-Key` prefix), `SUPERTEXT_API_URL`, environment, timeout and excluded fields in YAML.
- Tasks `supertext-translate` and `supertext-check`.
- Retries when the Supertext API answers HTTP 429 (rate limit).
- English and German CMS strings.
- Demo for Railway (`demo/`) with demo accounts, an Editors group, four locales and sample pages with Elemental blocks created on every start.
