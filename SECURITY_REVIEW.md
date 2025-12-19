# Security Review

## Scope and approach
- Reviewed server-side controllers and supporting services for access control gaps and data handling.
- Audited front-end rendering code for unsafe DOM mutations and reflected/stored injection risks.

## Findings

### 1) Stored XSS via report names in dataset status sidebar
- The dataset status view concatenates report names into HTML and injects them with `innerHTML` without escaping. A report name containing markup will be rendered as executable HTML when the dataset status loads. 【F:js/dataset.js†L618-L623】
- Report names are persisted without sanitization during creation, so hostile markup can be stored and reused across sessions. 【F:lib/Service/ReportService.php†L200-L203】
- Impact: Any user who views the dataset sidebar could have their session hijacked or their data exfiltrated if an attacker can create or rename a report tied to that dataset (e.g., via shared accounts or compromised credentials).
- Suggested mitigation: escape report names before injecting them (e.g., use `textContent`/`innerText`) and sanitize/validate names on the server.

### 2) Stored XSS via dataload names in the dataload list
- Dataload names are written directly into the DOM with `innerHTML` after updates, allowing markup in the name field to execute on subsequent renders. 【F:js/dataset.js†L360-L388】
- The update service stores the provided name without escaping, so injected HTML persists. 【F:lib/Service/DataloadService.php†L101-L110】
- Impact: A user with dataload edit access can persistently inject scripts that execute for anyone viewing the dataset’s dataload tab, leading to session compromise or data theft.
- Suggested mitigation: write names with `textContent` and normalize/sanitize the name server-side before storage.

## Recommendations
- Enforce output encoding in the front-end for any user-controlled text inserted into the DOM.
- Add server-side validation/encoding for display names (reports, datasets, dataloads) to prevent storing HTML/JS payloads.
- Add regression tests that cover the identified rendering paths to ensure unsafe HTML is neutralized.
