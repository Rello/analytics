#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 Marcel Scherello
# SPDX-License-Identifier: AGPL-3.0-or-later
set -euo pipefail
trusted_dir=$(cd "$(dirname "$0")" && pwd)
source_dir=$(cd "$1" && pwd)
cd "$source_dir"
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
# Install outside Git so Composer does not embed the changing PR commit SHA.
# Copy only manifests; scripts/plugins remain disabled.
bundle_dir=$(mktemp -d "${RUNNER_TEMP:-/tmp}/analytics-vendor.XXXXXX")
trap 'rm -rf "$bundle_dir"' EXIT
cp composer.json composer.lock "$bundle_dir/"
cd "$bundle_dir"
export COMPOSER_ROOT_VERSION=dev-master
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

# Replace the old tree only after installation and validation succeeded.
rm -rf "$source_dir/vendor"
mv vendor "$source_dir/vendor"
