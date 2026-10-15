#!/usr/bin/env python3
# SPDX-FileCopyrightText: 2026 Marcel Scherello
# SPDX-License-Identifier: AGPL-3.0-or-later
"""Validate Dependabot PR targets and publish generated vendor files without executing them."""
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys


def run(*args):
    return subprocess.check_output(args, text=True).strip()


def validate(pr, repository, event):
    if pr['state'] != 'open' or pr['base']['ref'] != 'master':
        raise ValueError('Target must be an open PR against master')
    if pr['head']['repo']['full_name'] != repository:
        raise ValueError('Fork PRs are not supported')
    if event != 'workflow_dispatch' and pr['user']['login'] != 'dependabot[bot]':
        raise ValueError('Automatic synchronization is limited to Dependabot PRs')
    sha, branch = pr['head']['sha'], pr['head']['ref']
    if not re.fullmatch(r'[0-9a-f]{40}', sha) or '\n' in branch or '\r' in branch:
        raise ValueError('Invalid PR reference')
    if branch == 'master':
        raise ValueError('Refusing to write directly to master')
    run('git', 'check-ref-format', 'refs/heads/' + branch)
    return sha, branch


def target():
    number = os.environ['PR_NUMBER']
    if not number.isdecimal():
        raise ValueError('PR number must be numeric')
    repository = os.environ['GITHUB_REPOSITORY']
    pr = json.loads(run('gh', 'api', f'repos/{repository}/pulls/{number}'))
    sha, branch = validate(pr, repository, os.environ['GITHUB_EVENT_NAME'])
    return number, sha, branch


def copy_vendor(source, destination):
    if not (source / 'composer/installed.json').is_file():
        raise ValueError('Missing installed package metadata')
    for path in source.rglob('*'):
        if path.is_symlink() or '.git' in path.relative_to(source).parts or not (path.is_file() or path.is_dir()):
            raise ValueError(f'Unsupported artifact path: {path}')
    if destination.is_symlink():
        raise ValueError('Refusing to replace a symlinked vendor directory')
    if destination.exists():
        shutil.rmtree(destination)
    shutil.copytree(source, destination)


def main():
    number, sha, branch = target()
    if sys.argv[1] == 'target':
        with open(os.environ['GITHUB_OUTPUT'], 'a') as output:
            output.write(f'number={number}\nsha={sha}\nbranch={branch}\n')
        return
    if (sha, branch) != (os.environ['EXPECTED_SHA'], os.environ['EXPECTED_BRANCH']):
        raise ValueError('PR changed during installation; rerun on its new head')
    run('gh', 'auth', 'setup-git')
    run('git', 'config', 'core.hooksPath', '/dev/null')
    run('git', 'fetch', 'origin', sha)
    run('git', 'checkout', '--detach', sha)
    copy_vendor(Path(os.environ['RUNNER_TEMP']) / 'bundled-vendor', Path('vendor'))
    run('git', 'add', '--all', '--', 'vendor')
    if not run('git', 'diff', '--cached', '--name-only'):
        print('Bundled libraries already match the lock file')
        return
    changed = run('git', 'diff', '--cached', '--name-only').splitlines()
    if any(not path.startswith('vendor/') for path in changed):
        raise ValueError('Refusing to commit files outside vendor/')
    run('git', 'config', 'user.name', 'Rello')
    run('git', 'config', 'user.email', 'github@scherello.de')
    run('git', 'commit', '-m', 'Synchronize bundled PHP libraries with composer.lock',
        '-m', 'Signed-off-by: Rello <github@scherello.de>', '-m', 'Assisted-by: Codex:GPT-5')
    # A normal push refuses races; never force-push over a newer Dependabot update.
    run('git', 'push', 'origin', f'HEAD:refs/heads/{branch}')
    print(f'Updated bundled libraries on PR #{number}')


if __name__ == '__main__':
    main()
