#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 Marcel Scherello
# SPDX-License-Identifier: AGPL-3.0-or-later
set -euo pipefail
trusted_dir=$(cd "$(dirname "$0")" && pwd)
cd "$1"
# PR manifests are data. Never execute their Composer scripts or plugins.
# Require the repository's standard layout before replacing the bundled tree.
python3 - <<'PY'
import json
from pathlib import Path
config = json.loads(Path('composer.json').read_text()).get('config', {})
if config.get('vendor-dir', 'vendor') != 'vendor' or config.get('bin-dir', 'vendor/bin') != 'vendor/bin':
    raise SystemExit('Unexpected Composer output directory')
if Path('vendor').is_symlink() or Path('build').is_symlink():
    raise SystemExit('Symlinked build/vendor directories are not supported')
PY
# A clean installation also removes obsolete files from previous versions.
rm -rf vendor
composer install --no-dev --prefer-dist --no-interaction --no-progress --no-plugins --no-scripts --no-autoloader
# Use the pruning code from the default branch, never from the PR.
mkdir -p build
cp "$trusted_dir/prune-phpspreadsheet.php" build/prune-phpspreadsheet.php
php build/prune-phpspreadsheet.php
composer dump-autoload --no-dev --optimize --no-plugins --no-scripts
python3 - <<'PY'
import json
from pathlib import Path
locked = json.loads(Path('composer.lock').read_text())['packages']
installed = json.loads(Path('vendor/composer/installed.json').read_text())['packages']
assert {p['name']: p['version'] for p in locked} == {p['name']: p['version'] for p in installed}, 'Bundled versions differ from lock file'
for path in Path('vendor').rglob('*'):
    if path.is_symlink() or '.git' in path.parts:
        raise SystemExit(f'Unsupported bundled path: {path}')
assert not Path('vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Writer').exists(), 'Pruning failed'
print('Locked versions and pruned vendor tree validated')
PY
