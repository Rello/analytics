#!/usr/bin/env sh
# SPDX-FileCopyrightText: 2026 Marcel Scherello
# SPDX-License-Identifier: AGPL-3.0-or-later

set -eu

SCRIPT_DIR=$(CDPATH= cd "$(dirname "$0")" && pwd)
ROOT_DIR=$(CDPATH= cd "$SCRIPT_DIR/.." && pwd)
OUTPUT_PATH=${1:-"$ROOT_DIR/sbom.cdx.json"}

if ! command -v ruby >/dev/null 2>&1; then
    echo "ruby is required to generate the SBOM" >&2
    exit 1
fi

ruby - "$ROOT_DIR" "$OUTPUT_PATH" <<'RUBY'
require 'cgi'
require 'digest'
require 'json'
require 'pathname'
require 'securerandom'
require 'time'

root = Pathname.new(ARGV.fetch(0)).realpath
output_path = Pathname.new(ARGV.fetch(1))
output_path = root.join(output_path) unless output_path.absolute?

def read_file(root, path)
  root.join(path).read
end

def read_json(root, path)
  JSON.parse(read_file(root, path))
end

def sha256_file(root, path)
  Digest::SHA256.file(root.join(path)).hexdigest
end

def directory_sha256(root, path)
  directory = root.join(path)
  files = Dir.glob(directory.join('**', '*'), File::FNM_DOTMATCH)
    .select { |file| File.file?(file) }
    .map { |file| Pathname.new(file).relative_path_from(root).to_s }
    .sort

  digest = Digest::SHA256.new
  files.each do |file|
    digest.update(sha256_file(root, file))
    digest.update('  ')
    digest.update(file)
    digest.update("\n")
  end
  digest.hexdigest
end

def detect_version(root, path, pattern, fallback = 'unknown')
  content = read_file(root, path)
  match = content.match(pattern)
  match ? match[1] : fallback
end

def license(id)
  [{ 'license' => { 'id' => id } }]
end

def property(name, value)
  { 'name' => name, 'value' => value }
end

def hash_entry(root, path)
  { 'alg' => 'SHA-256', 'content' => sha256_file(root, path) }
end

def website_reference(url)
  { 'type' => 'website', 'url' => url }
end

def vcs_reference(url)
  { 'type' => 'vcs', 'url' => url }
end

def composer_purl(name, version)
  "pkg:composer/#{name}@#{version}"
end

def npm_purl(name, version)
  "pkg:npm/#{name}@#{CGI.escape(version).gsub('+', '%20')}"
end

def package_homepage(package)
  return package['homepage'] if package['homepage']

  source_url = package.dig('source', 'url')
  return nil unless source_url

  source_url.sub(/\.git\z/, '')
end

def package_component(root, package)
  group, name = package.fetch('name').split('/', 2)
  version = package.fetch('version')
  package_path = "vendor/#{package.fetch('name')}"

  unless root.join(package_path).directory?
    raise "Missing bundled package directory: #{package_path}"
  end

  external_references = []
  external_references << vcs_reference(package.dig('source', 'url')) if package.dig('source', 'url')
  homepage = package_homepage(package)
  external_references << website_reference(homepage) if homepage

  properties = [
    property('local:path', package_path),
    property('local:directory-sha256', directory_sha256(root, package_path)),
    property('local:metadata-source', 'composer.lock'),
  ]
  properties << property('vcs:reference', package.dig('source', 'reference')) if package.dig('source', 'reference')

  {
    'type' => 'library',
    'bom-ref' => composer_purl(package.fetch('name'), version),
    'group' => group,
    'name' => name,
    'version' => version,
    'description' => package['description'],
    'scope' => 'required',
    'licenses' => license((package['license'] || ['NOASSERTION']).first),
    'purl' => composer_purl(package.fetch('name'), version),
    'externalReferences' => external_references,
    'properties' => properties,
  }
end

def browser_component(root, name:, version:, bom_ref:, path:, website: nil)
  component = {
    'type' => 'library',
    'bom-ref' => bom_ref,
    'group' => 'npm',
    'name' => name,
    'version' => version,
    'scope' => 'required',
    'hashes' => [hash_entry(root, path)],
    'licenses' => license('MIT'),
    'purl' => bom_ref,
    'properties' => [property('local:path', path)],
  }
  component['externalReferences'] = [website_reference(website)] if website
  component
end

def datatables_component(root, name:, version:, bom_ref:, website:)
  {
    'type' => 'library',
    'bom-ref' => bom_ref,
    'group' => 'npm',
    'name' => name,
    'version' => version,
    'scope' => 'required',
    'hashes' => [
      hash_entry(root, 'js/3rdParty/datatables.min.js'),
      hash_entry(root, 'css/3rdParty/datatables.min.css'),
    ],
    'licenses' => license('MIT'),
    'purl' => bom_ref,
    'externalReferences' => [website_reference(website)],
    'properties' => [
      property('local:path', 'js/3rdParty/datatables.min.js'),
      property('local:path', 'css/3rdParty/datatables.min.css'),
    ],
  }
end

def serial_number
  serial = ENV['SBOM_SERIAL']
  return serial.start_with?('urn:uuid:') ? serial : "urn:uuid:#{serial}" if serial && !serial.empty?

  "urn:uuid:#{SecureRandom.uuid}"
end

def timestamp
  ENV['SBOM_TIMESTAMP'] && !ENV['SBOM_TIMESTAMP'].empty? ? ENV['SBOM_TIMESTAMP'] : Time.now.utc.iso8601
end

info_xml = read_file(root, 'appinfo/info.xml')
app_id = info_xml.match(%r{<id>([^<]+)</id>})&.[](1) || 'analytics'
app_version = info_xml.match(%r{<version>([^<]+)</version>})&.[](1) || 'unknown'
composer_json = read_json(root, 'composer.json')
composer_lock = read_json(root, 'composer.lock')
composer_packages = composer_lock.fetch('packages', [])
composer_package_refs = composer_packages.to_h do |package|
  [package.fetch('name'), composer_purl(package.fetch('name'), package.fetch('version'))]
end

components = [
  {
    'type' => 'platform',
    'bom-ref' => 'platform:php',
    'name' => 'PHP',
    'version' => composer_json.fetch('require').fetch('php'),
    'scope' => 'required',
    'purl' => 'pkg:generic/php',
    'cpe' => 'cpe:2.3:a:php:php:*:*:*:*:*:*:*:*',
    'properties' => [
      property('composer:constraint', composer_json.fetch('require').fetch('php')),
      property('local:source', 'composer.json'),
      property('local:sha256', sha256_file(root, 'composer.json')),
    ],
  },
  {
    'type' => 'framework',
    'bom-ref' => 'pkg:github/nextcloud/server',
    'group' => 'nextcloud',
    'name' => 'server',
    'version' => '>=29 <=99',
    'scope' => 'required',
    'purl' => 'pkg:github/nextcloud/server',
    'properties' => [property('local:source', 'appinfo/info.xml')],
  },
]
components.concat(composer_packages.map { |package| package_component(root, package) })

chart_version = detect_version(root, 'js/3rdParty/chart.umd.js', /Chart\.js v([0-9.]+)/)
components << browser_component(
  root,
  name: 'chart.js',
  version: chart_version,
  bom_ref: npm_purl('chart.js', chart_version),
  path: 'js/3rdParty/chart.umd.js',
  website: 'https://www.chartjs.org'
)

adapter_version = detect_version(root, 'js/3rdParty/chartjs-adapter-moment.js', /chartjs-adapter-moment v([0-9.]+)/)
components << browser_component(
  root,
  name: 'chartjs-adapter-moment',
  version: adapter_version,
  bom_ref: npm_purl('chartjs-adapter-moment', adapter_version),
  path: 'js/3rdParty/chartjs-adapter-moment.js',
  website: 'https://www.chartjs.org'
)

annotation_version = detect_version(root, 'js/3rdParty/chartjs-plugin-annotation.min.js', /chartjs-plugin-annotation v([0-9.]+)/)
components << browser_component(
  root,
  name: 'chartjs-plugin-annotation',
  version: annotation_version,
  bom_ref: npm_purl('chartjs-plugin-annotation', annotation_version),
  path: 'js/3rdParty/chartjs-plugin-annotation.min.js',
  website: 'https://www.chartjs.org/chartjs-plugin-annotation/latest/'
)

colorschemes_version = detect_version(root, 'js/3rdParty/chartjs-plugin-colorschemes.min.js', /chartjs-plugin-colorschemes v([0-9.]+)/)
components << browser_component(
  root,
  name: 'chartjs-plugin-colorschemes',
  version: colorschemes_version,
  bom_ref: npm_purl('chartjs-plugin-colorschemes', colorschemes_version),
  path: 'js/3rdParty/chartjs-plugin-colorschemes.min.js',
  website: 'https://nagix.github.io/chartjs-plugin-colorschemes'
)

datalabels_version = detect_version(root, 'js/3rdParty/chartjs-plugin-datalabels.min.js', /chartjs-plugin-datalabels v([0-9.]+)/)
components << browser_component(
  root,
  name: 'chartjs-plugin-datalabels',
  version: datalabels_version,
  bom_ref: npm_purl('chartjs-plugin-datalabels', datalabels_version),
  path: 'js/3rdParty/chartjs-plugin-datalabels.min.js',
  website: 'https://chartjs-plugin-datalabels.netlify.app'
)

zoom_version = detect_version(root, 'js/3rdParty/chartjs-plugin-zoom.min.js', /chartjs-plugin-zoom v([0-9.]+)/)
components << browser_component(
  root,
  name: 'chartjs-plugin-zoom',
  version: zoom_version,
  bom_ref: npm_purl('chartjs-plugin-zoom', zoom_version),
  path: 'js/3rdParty/chartjs-plugin-zoom.min.js'
)

components << {
  'type' => 'library',
  'bom-ref' => 'local:chartjs-plugin-funnel',
  'group' => 'npm',
  'name' => 'chartjs-plugin-funnel',
  'version' => 'unknown',
  'scope' => 'required',
  'purl' => 'pkg:npm/chartjs-chart-funnel',
  'hashes' => [hash_entry(root, 'js/3rdParty/chartjs-plugin-funnel.min.js')],
  'licenses' => license('MIT'),
  'externalReferences' => [website_reference('https://github.com/sgratzl/chartjs-chart-funnel')],
  'properties' => [
    property('local:path', 'js/3rdParty/chartjs-plugin-funnel.min.js'),
    property('local:package-evidence', 'Bundled file is referenced as chartjs-plugin-funnel locally; upstream reference points to chartjs-chart-funnel.'),
    property('local:version-evidence', 'No plugin version header found in the bundled minified file.'),
  ],
}

components << {
  'type' => 'library',
  'bom-ref' => 'local:cloner.js',
  'name' => 'cloner.js',
  'version' => 'unknown',
  'scope' => 'required',
  'purl' => 'pkg:generic/cloner.js@unknown',
  'hashes' => [hash_entry(root, 'js/3rdParty/cloner.js')],
  'licenses' => license('MIT'),
  'properties' => [
    property('local:path', 'js/3rdParty/cloner.js'),
    property('local:copyright', 'Andrea Giammarchi - @WebReflection'),
    property('local:version-evidence', 'No version header found in the bundled file.'),
  ],
}

datatables_version = detect_version(root, 'js/3rdParty/datatables.min.js', /DataTables\s+([0-9.]+)/)
components << datatables_component(
  root,
  name: 'datatables.net',
  version: datatables_version,
  bom_ref: npm_purl('datatables.net', datatables_version),
  website: 'https://datatables.net'
)

colreorder_version = detect_version(root, 'js/3rdParty/datatables.min.js', /ColReorder\s+([0-9.]+)/)
components << datatables_component(
  root,
  name: 'datatables.net-colreorder',
  version: colreorder_version,
  bom_ref: npm_purl('datatables.net-colreorder', colreorder_version),
  website: 'https://datatables.net/extensions/colreorder/'
)

html2canvas_version = detect_version(root, 'js/3rdParty/html2canvas.min.js', /html2canvas\s+([0-9.]+)/)
components << browser_component(
  root,
  name: 'html2canvas',
  version: html2canvas_version,
  bom_ref: npm_purl('html2canvas', html2canvas_version),
  path: 'js/3rdParty/html2canvas.min.js',
  website: 'https://html2canvas.hertzen.com'
)

jquery_version = detect_version(root, 'js/3rdParty/jquery.min.js', /jQuery v([^ |]+)/)
components << browser_component(
  root,
  name: 'jquery',
  version: jquery_version,
  bom_ref: npm_purl('jquery', jquery_version),
  path: 'js/3rdParty/jquery.min.js',
  website: 'https://jquery.com'
)

jspdf_version = detect_version(root, 'js/3rdParty/jspdf.umd.min.js', /Version\s+([0-9.]+)/)
components << browser_component(
  root,
  name: 'jspdf',
  version: jspdf_version,
  bom_ref: npm_purl('jspdf', jspdf_version),
  path: 'js/3rdParty/jspdf.umd.min.js',
  website: 'https://github.com/parallax/jsPDF'
)

moment_version = detect_version(root, 'js/3rdParty/moment.min.js', /version\s*[:=]\s*"?([0-9.]+)/)
components << browser_component(
  root,
  name: 'moment',
  version: moment_version,
  bom_ref: npm_purl('moment', moment_version),
  path: 'js/3rdParty/moment.min.js',
  website: 'https://momentjs.com'
)

component_refs = components.to_h { |component| [component['name'], component['bom-ref']] }
browser_dependency_refs = [
  component_refs.fetch('chart.js'),
  component_refs.fetch('chartjs-adapter-moment'),
  component_refs.fetch('chartjs-plugin-annotation'),
  component_refs.fetch('chartjs-plugin-colorschemes'),
  component_refs.fetch('chartjs-plugin-datalabels'),
  component_refs.fetch('chartjs-plugin-funnel'),
  component_refs.fetch('chartjs-plugin-zoom'),
  component_refs.fetch('cloner.js'),
  component_refs.fetch('datatables.net'),
  component_refs.fetch('datatables.net-colreorder'),
  component_refs.fetch('html2canvas'),
  component_refs.fetch('jquery'),
  component_refs.fetch('jspdf'),
  component_refs.fetch('moment'),
]

root_dependencies = ['platform:php', 'pkg:github/nextcloud/server']
composer_json.fetch('require').each_key do |package_name|
  next if package_name == 'php'
  root_dependencies << composer_package_refs.fetch(package_name) if composer_package_refs.key?(package_name)
end
root_dependencies.concat(browser_dependency_refs)

dependencies = [
  {
    'ref' => "pkg:github/Rello/analytics@#{app_version}",
    'dependsOn' => root_dependencies.uniq,
  },
]

composer_packages.each do |package|
  depends_on = []
  package.fetch('require', {}).each_key do |package_name|
    if package_name == 'php' || package_name.start_with?('php-')
      depends_on << 'platform:php'
    elsif composer_package_refs.key?(package_name)
      depends_on << composer_package_refs.fetch(package_name)
    end
  end
  dependencies << {
    'ref' => composer_package_refs.fetch(package.fetch('name')),
    'dependsOn' => depends_on.uniq,
  }
end

dependencies.concat([
  {
    'ref' => component_refs.fetch('chartjs-adapter-moment'),
    'dependsOn' => [component_refs.fetch('chart.js'), component_refs.fetch('moment')],
  },
  {
    'ref' => component_refs.fetch('chartjs-plugin-annotation'),
    'dependsOn' => [component_refs.fetch('chart.js')],
  },
  {
    'ref' => component_refs.fetch('chartjs-plugin-colorschemes'),
    'dependsOn' => [component_refs.fetch('chart.js')],
  },
  {
    'ref' => component_refs.fetch('chartjs-plugin-datalabels'),
    'dependsOn' => [component_refs.fetch('chart.js')],
  },
  {
    'ref' => component_refs.fetch('chartjs-plugin-funnel'),
    'dependsOn' => [component_refs.fetch('chart.js')],
  },
  {
    'ref' => component_refs.fetch('chartjs-plugin-zoom'),
    'dependsOn' => [component_refs.fetch('chart.js')],
  },
])

phpspreadsheet = composer_packages.find { |package| package.fetch('name') == 'phpoffice/phpspreadsheet' }

bom = {
  'bomFormat' => 'CycloneDX',
  'specVersion' => '1.5',
  'serialNumber' => serial_number,
  'version' => 1,
  'metadata' => {
    'timestamp' => timestamp,
    'tools' => [
      {
        'vendor' => 'OpenAI',
        'name' => 'Codex local manifest extraction',
        'version' => '2026-07-07',
      },
    ],
    'supplier' => {
      'name' => 'Marcel Scherello',
      'url' => ['https://github.com/Rello/analytics'],
      'contact' => [
        {
          'name' => 'Marcel Scherello',
          'email' => 'analytics@scherello.de',
        },
      ],
    },
    'component' => {
      'type' => 'application',
      'bom-ref' => "pkg:github/Rello/analytics@#{app_version}",
      'name' => app_id,
      'version' => app_version,
      'description' => 'Nextcloud Analytics app',
      'licenses' => license('AGPL-3.0-or-later'),
      'purl' => "pkg:github/Rello/analytics@#{app_version}",
      'externalReferences' => [
        website_reference('https://rello.github.io/analytics/'),
        vcs_reference('https://github.com/Rello/analytics'),
        {
          'type' => 'issue-tracker',
          'url' => 'https://github.com/Rello/analytics/issues',
        },
      ],
      'properties' => [
        property('nextcloud:app-id', app_id),
        property('local:source', 'appinfo/info.xml'),
        property('local:sha256', sha256_file(root, 'appinfo/info.xml')),
      ],
    },
    'properties' => [
      property('local:generated-from', 'appinfo/info.xml, composer.json, composer.lock, vendor/composer/installed.json, js/3rdParty, css/3rdParty'),
      property('local:note', "Root composer.json requires phpoffice/phpspreadsheet #{composer_json.fetch('require').fetch('phpoffice/phpspreadsheet')}; composer.lock records phpoffice/phpspreadsheet #{phpspreadsheet&.fetch('version', 'unknown')}."),
      property('local:note', 'Composer packages are taken from composer.lock and verified against the bundled vendor package directories.'),
    ],
  },
  'components' => components,
  'dependencies' => dependencies,
}

output_path.dirname.mkpath
output_path.write(JSON.pretty_generate(bom) + "\n")
RUBY

printf 'Wrote %s\n' "$OUTPUT_PATH"
