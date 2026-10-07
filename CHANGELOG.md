# Changelog

All notable changes to this project are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/).

## Unreleased

### Added

- The **Supertext** CMS section shows the installed plugin version under *Connection*, linked to its GitHub release notes for released versions.

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
