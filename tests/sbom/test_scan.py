# SPDX-FileCopyrightText: 2026 Marcel Scherello
# SPDX-License-Identifier: AGPL-3.0-or-later
import importlib.util
from pathlib import Path
import unittest

spec = importlib.util.spec_from_file_location('scan_sbom', Path(__file__).resolve().parents[2] / 'build/scan-sbom.py')
scanner = importlib.util.module_from_spec(spec)
spec.loader.exec_module(scanner)
PURL = 'pkg:composer/example/library@1.0.0'
BOM = {'components': [{'name': 'library', 'version': '1.0.0', 'purl': PURL}]}
ADVISORY = {'id': 'GHSA-test', 'aliases': ['CVE-TEST'], 'summary': 'Test finding'}


class ScanTest(unittest.TestCase):
    def test_pagination_withdrawals_and_unknown_versions(self):
        calls = []
        def fetch(url, payload):
            calls.append(payload.copy())
            if 'page_token' not in payload:
                return {'vulns': [ADVISORY], 'next_page_token': 'page2'}
            return {'vulns': [ADVISORY, {'id': 'withdrawn', 'withdrawn': '2026-01-01'}]}
        report = scanner.scan({'components': BOM['components'] + [{'name': 'unknown', 'version': 'unknown'}]}, fetch)
        self.assertEqual(len(report['findings']), 1)
        self.assertEqual(len(report['skipped']), 1)
        self.assertEqual(calls[1]['page_token'], 'page2')

    def test_network_failure_aborts_scan(self):
        def fail(*args):
            raise TimeoutError('unavailable')
        with self.assertRaises(TimeoutError):
            scanner.scan(BOM, fail)

    def test_no_supported_components_fails(self):
        with self.assertRaises(ValueError):
            scanner.scan({'components': []})

    def test_issue_creation_deduplication_resolution_and_recurrence(self):
        report = scanner.scan(BOM, lambda *args: {'vulns': [ADVISORY]})
        issues, writes = [], []
        def api(path, method='GET', payload=None):
            if method == 'GET':
                return issues.copy()
            writes.append((method, payload))
            if method == 'POST':
                issues.append(dict(payload, number=1, state='open'))
            else:
                issues[0].update(payload)
        scanner.reconcile(report, 'owner/repo', api)
        self.assertEqual(len(writes), 1)
        scanner.reconcile(report, 'owner/repo', api)
        self.assertEqual(len(writes), 1)
        # Missing coverage must not resolve a finding.
        scanner.reconcile({'checked': [], 'findings': []}, 'owner/repo', api)
        self.assertEqual(issues[0]['state'], 'open')
        scanner.reconcile({'checked': [PURL.replace('1.0.0', '1.0.1')], 'findings': []}, 'owner/repo', api)
        self.assertEqual(issues[0]['state'], 'closed')
        scanner.reconcile(report, 'owner/repo', api)
        self.assertEqual(issues[0]['state'], 'open')
        issues[0].update(state='closed', state_reason='not_planned')
        scanner.reconcile(report, 'owner/repo', api)
        self.assertEqual(issues[0]['state'], 'closed')


if __name__ == '__main__':
    unittest.main()
