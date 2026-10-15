# SPDX-FileCopyrightText: 2026 Marcel Scherello
# SPDX-License-Identifier: AGPL-3.0-or-later
import importlib.util
from pathlib import Path
import tempfile
import unittest

spec = importlib.util.spec_from_file_location('vendor_sync', Path(__file__).resolve().parents[2] / 'build/dependabot-vendor.py')
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)


class VendorSyncTest(unittest.TestCase):
    def pr(self):
        return {'state': 'open', 'base': {'ref': 'master'}, 'head': {'repo': {'full_name': 'Rello/analytics'}, 'ref': 'dependabot/composer/test', 'sha': 'a' * 40}, 'user': {'login': 'dependabot[bot]'}}

    def test_only_same_repository_dependabot_prs_automatically(self):
        pr = self.pr()
        module.validate(pr, 'Rello/analytics', 'pull_request_target')
        pr['head']['repo']['full_name'] = 'other/analytics'
        with self.assertRaises(ValueError):
            module.validate(pr, 'Rello/analytics', 'pull_request_target')
        pr = self.pr()
        pr['user']['login'] = 'other'
        with self.assertRaises(ValueError):
            module.validate(pr, 'Rello/analytics', 'pull_request_target')
        module.validate(pr, 'Rello/analytics', 'workflow_dispatch')
        pr['state'] = 'closed'
        with self.assertRaises(ValueError):
            module.validate(pr, 'Rello/analytics', 'workflow_dispatch')

    def test_artifact_rejects_symlinks_and_git_metadata(self):
        with tempfile.TemporaryDirectory() as root:
            source, dest = Path(root) / 'source', Path(root) / 'vendor'
            (source / 'composer').mkdir(parents=True)
            (source / 'composer/installed.json').write_text('{}')
            (source / 'link').symlink_to('/tmp')
            with self.assertRaises(ValueError):
                module.copy_vendor(source, dest)
            (source / 'link').unlink()
            (source / '.git').mkdir()
            with self.assertRaises(ValueError):
                module.copy_vendor(source, dest)
            (source / '.git').rmdir()
            dest.mkdir()
            (dest / 'obsolete').write_text('old')
            module.copy_vendor(source, dest)
            self.assertFalse((dest / 'obsolete').exists())
            self.assertTrue((dest / 'composer/installed.json').exists())
