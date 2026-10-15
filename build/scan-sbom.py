#!/usr/bin/env python3
# SPDX-FileCopyrightText: 2026 Marcel Scherello
# SPDX-License-Identifier: AGPL-3.0-or-later
"""Scan exact bundled package versions with OSV; reconcile only managed issues."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import subprocess
import time
import urllib.error
import urllib.request

LABEL = 'sbom-security'
MARKER = '<!-- analytics-sbom:'


def request(url, payload=None):
    data = None if payload is None else json.dumps(payload).encode()
    for attempt in range(3):
        try:
            req = urllib.request.Request(url, data=data, headers={'Content-Type': 'application/json'})
            with urllib.request.urlopen(req, timeout=45) as response:
                return json.load(response)
        except (urllib.error.URLError, TimeoutError):
            if attempt == 2:
                raise
            time.sleep(2 ** attempt)


def scan(bom, fetch=request):
    findings, checked, skipped = {}, [], []
    for component in bom['components']:
        purl = component.get('purl', '')
        if not purl.startswith(('pkg:composer/', 'pkg:npm/')) or '@' not in purl or component.get('version') in (None, '', 'unknown'):
            skipped.append({'name': component['name'], 'version': component.get('version'), 'reason': 'No supported exact package version'})
            continue
        checked.append(purl)
        payload = {'package': {'purl': purl}}
        tokens = set()
        while True:
            result = fetch('https://api.osv.dev/v1/query', payload)
            if not isinstance(result, dict) or not isinstance(result.get('vulns', []), list):
                raise ValueError('Invalid OSV response')
            for advisory in result.get('vulns', []):
                if advisory.get('withdrawn'):
                    continue
                identity = purl.rsplit('@', 1)[0]
                key = hashlib.sha256((identity + ':' + advisory['id']).encode()).hexdigest()
                findings[key] = {'key': key, 'package': identity, 'purl': purl, 'advisory': advisory}
            token = result.get('next_page_token')
            if not token:
                break
            if token in tokens:
                raise ValueError('Repeated OSV pagination token')
            tokens.add(token)
            payload = {'package': {'purl': purl}, 'page_token': token}
    if not checked:
        raise ValueError('No packages could be checked')
    return {'checked': checked, 'skipped': skipped, 'findings': list(findings.values())}


def gh(path, method='GET', payload=None):
    args = ['gh', 'api', path, '--method', method]
    if payload is not None:
        args += ['--input', '-']
    result = subprocess.run(args, input=None if payload is None else json.dumps(payload), text=True, check=True, capture_output=True)
    return json.loads(result.stdout) if result.stdout.strip() else None


def issue_text(finding):
    advisory = finding['advisory']
    aliases = ', '.join(advisory.get('aliases', [])) or advisory['id']
    severity = advisory.get('database_specific', {}).get('severity', 'UNKNOWN')
    fixes = sorted({event['fixed'] for affected in advisory.get('affected', [])
                    if affected.get('package', {}).get('purl') == finding['package']
                    for interval in affected.get('ranges', []) for event in interval.get('events', []) if 'fixed' in event})
    title = f"[SBOM] {aliases}: {finding['package'].split('/', 1)[1]}"
    body = f"""{MARKER}{finding['key']} -->
Known vulnerability matched by OSV against the bundled dependency SBOM.

- Package: `{finding['purl']}`
- Advisory: [{advisory['id']}](https://osv.dev/vulnerability/{advisory['id']})
- Identifiers: {aliases}
- Severity: {severity}
- Summary: {advisory.get('summary', '')}
- Upstream fixed versions (choose the appropriate release branch): {', '.join(fixes) or 'Not listed'}

This is a dependency-version match, not proof of exploitation in Analytics. Review the advisory's runtime conditions; browser-only usage can make server-side findings inapplicable.

Update the shipped dependency and its metadata, then push to the default branch or run **SBOM security** with refresh enabled. Composer lock-file changes alone do not update bundled vendor code.

The workflow closes this issue only after the package is successfully checked without this advisory. Close as **not planned** to record an accepted/inapplicable finding; add the reason in a comment. Such issues remain closed while the match persists.
"""
    return title[:256], body


def reconcile(report, repository, api=gh):
    base = f'repos/{repository}'
    # Fetch all pages before changing anything; never treat a failed listing as empty.
    issues, page = [], 1
    while True:
        batch = api(f'{base}/issues?state=all&labels={LABEL}&per_page=100&page={page}')
        issues.extend(i for i in batch if 'pull_request' not in i)
        if len(batch) < 100:
            break
        page += 1
    known = {}
    for issue in issues:
        body = issue.get('body') or ''
        if body.startswith(MARKER):
            key = body.split(' -->', 1)[0][len(MARKER):]
            known[key] = issue
    active = set()
    for finding in report['findings']:
        key = finding['key']
        active.add(key)
        title, body = issue_text(finding)
        issue = known.get(key)
        if issue is None:
            api(f'{base}/issues', 'POST', {'title': title, 'body': body, 'labels': [LABEL]})
        elif issue['state'] == 'closed' and issue.get('state_reason') == 'not_planned':
            continue
        elif issue['state'] == 'closed' or issue['title'] != title or issue['body'] != body:
            api(f"{base}/issues/{issue['number']}", 'PATCH', {'title': title, 'body': body, 'state': 'open'})
    for key, issue in known.items():
        if key not in active and issue['state'] == 'open':
            # Only resolve packages actually checked, not missing/unknown inventory entries.
            body = issue.get('body') or ''
            if any(f"`{purl.rsplit('@', 1)[0]}@" in body for purl in report['checked']):
                api(f"{base}/issues/{issue['number']}", 'PATCH', {'state': 'closed', 'state_reason': 'completed'})


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--issues', action='store_true')
    parser.add_argument('--sbom', default='sbom.cdx.json')
    parser.add_argument('--output', default='sbom-security-report.json')
    args = parser.parse_args()
    raw = Path(args.sbom).read_bytes()
    # Finish every OSV query before any issue writes. Failures never resolve issues.
    report = scan(json.loads(raw))
    report['sbom_sha256'] = hashlib.sha256(raw).hexdigest()
    report['scanned_at'] = time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime())
    Path(args.output).write_text(json.dumps(report, indent=2) + '\n')
    summary = f"OSV: {len(report['checked'])} packages checked, {len(report['findings'])} findings, {len(report['skipped'])} coverage gaps."
    print(summary)
    if os.environ.get('GITHUB_STEP_SUMMARY'):
        with open(os.environ['GITHUB_STEP_SUMMARY'], 'a') as stream:
            stream.write(summary + '\n\n')
            for finding in report['findings']:
                stream.write(f"- {finding['purl']}: {finding['advisory']['id']}\n")
            for gap in report['skipped']:
                stream.write(f"- Not scanned: {gap['name']} ({gap['version']})\n")
    if args.issues:
        reconcile(report, os.environ['GITHUB_REPOSITORY'])
    # Findings are tracked as issues; execution failures fail the workflow.


if __name__ == '__main__':
    main()
