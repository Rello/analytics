
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

For every change, add a meaningful one-liner to the corresponding section (Added, Changed, Fixed) in CHANGELOG.md.

No nodejs or vue components are used. Everything is plain Javascript.