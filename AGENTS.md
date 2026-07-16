
# Project Agents.md Guide for OpenAI Codex

This `AGENTS.md` file provides guidelines for OpenAI Codex and other AI agents interacting with this codebase, including which directories are safe to read from or write to.

## Project Structure: AI Agent Handling Guidelines

| Directory       | Description                                         | Agent Action         |
|-----------------|-----------------------------------------------------|----------------------|
| `/vendor`       | External plugins; may help understand data sources. | Do not modify        |
| `/l10n`         | Translation files from Transifex.                   | Do not modify        |
| `/js/3rdParty`  | Third-party JavaScript plugins.                     | Do not modify        |
| `/css/3rdParty` | Third-party CSS plugins.                            | Do not modify        |
| `/sample_data`  | Example data for human reference.                   | Irrelevant to agents |
| `/screenshots`  | UI images for documentation purposes.               | Irrelevant to agents |
| `/tests`        | phpUnit and SeleniumIDE test cases                  | use if possible      |

## General Guidance

Agents should focus on the core application logic and ignore files or folders marked as third-party, sample, or media-related. All changes should preserve the integrity of external dependencies and translations.

For every change, add a meaningful one-liner to the corresponding section (Added, Changed, Fixed) in CHANGELOG.md. Do not add entries to a closed milestone which has a data. if no open milestone exists, add a new section to the changelog.

Test execution instructions are maintained in `tests/INSTRUCTIONS.md`; prefer the reusable wrappers `tests/run-unit.sh` and `tests/run-playwright.sh` over ad-hoc container commands.

No nodejs or vue components are used. Everything is plain Javascript. 

Do not use ILogger. It is deprecated in favor of Psr\Log\LoggerInterface

### WhatsNew Migrations

When adding release notes for the `/whatsnew` endpoint, create a dedicated migration in `lib/Migration` that inserts one row into `analytics_whats_new`.

- Use the current app version from `appinfo/info.xml` as the `version` value, for example `6.4.0`.
- Name the migration with the numeric app version and timestamp pattern used by existing migrations, for example `Version6400Date20260604100000.php`.
- Add the standard SPDX header for new files.
- Inject `OCP\IDBConnection` in the constructor and insert the row in `postSchemaChange()`.
- Store `data` as the existing JSON structure with `changelogURL` and `whatsNew` keys. Provide at least `en` and `de` entries, each with `regular` and `admin` arrays.
- Keep `admin` consistent with previous entries unless the release note is admin-specific: English `New Features apply to users`, German `Nur User Features`.
- Do not add this migration to the `CHANGELOG.md`.

### License Header

Every new file needs to get a SPDX header in the first rows according to this template.
The year in the first line must be replaced with the year when the file is created (for example, 2026 for files first added in 2026).
The commenting signs need to be used depending on the file type.
If a file can not get a header like svg images, these need to be added to the REUSE.toml file.

```plaintext
SPDX-FileCopyrightText: <YEAR> Marcel Scherello
SPDX-License-Identifier: AGPL-3.0-or-later
```
