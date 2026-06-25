<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Datasource;

use OCP\IL10N;
use Psr\Log\LoggerInterface;

class Github implements IDatasource, IReportTemplateProvider {
	private const CACHE_TTL_SECONDS = 60;

	private LoggerInterface $logger;
	private IL10N $l10n;

	public function __construct(
		IL10N           $l10n,
		LoggerInterface $logger
	) {
		$this->l10n = $l10n;
		$this->logger = $logger;
	}

	/**
    * @return string Display Name of the data source
	 */
	public function getName(): string {
		return 'GitHub';
	}

	/**
    * @return int digit unique data source id
	 */
	public function getId(): int {
		return 3;
	}

	/**
    * @return array available options of the data source
	 */
	public function getTemplate(): array {
		$template = array();
		$template[] = ['id' => 'user', 'name' => 'GitHub Username', 'placeholder' => 'GitHub user'];
		$template[] = ['id' => 'repository', 'name' => 'Repository / package', 'placeholder' => 'GitHub repository or package'];
		$template[] = [
			'id' => 'data',
			'name' => 'Releases, Packages, Issues or PRs',
			'placeholder' => 'release-' . $this->l10n->t('Releases') . '/package-' . $this->l10n->t('Packages') . '/issues-' . $this->l10n->t('Issues') . '/pulls-' . $this->l10n->t('Pull Requests'),
			'type' => 'tf'
		];
		$template[] = [
			'id' => 'limit',
			'name' => $this->l10n->t('Limit'),
			'placeholder' => $this->l10n->t('Number of rows'),
			'type' => 'number'
		];
		$template[] = [
			'id' => 'timestamp',
			'name' => $this->l10n->t('Timestamp of data load'),
			'placeholder' => 'false-' . $this->l10n->t('No') . '/true-' . $this->l10n->t('Yes'),
			'type' => 'tf'
		];
		$template[] = ['id' => 'section', 'name' => $this->l10n->t('More options'), 'type' => 'section'];
		$template[] = [
			'id' => 'filter',
			'name' => $this->l10n->t('Filter release assets via extension'),
			'placeholder' => 'zip,gz'
		];
		$template[] = [
			'id' => 'showAssets',
			'name' => $this->l10n->t('Show assets'),
			'placeholder' => 'false-' . $this->l10n->t('No') . '/true-' . $this->l10n->t('Yes'),
			'type' => 'tf'
		];
		$template[] = [
			'id' => 'token',
			'name' => $this->l10n->t('Personal access token'),
			'placeholder' => $this->l10n->t('optional')
		];
		return $template;
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public function getReportTemplates(): array {
		return [
			'github_demo_downloads' => [
				'name' => 'GitHub Download Statistics',
				'report' => [
					'name' => 'GitHub Download Statistics',
					'subheader' => 'Realtime download statistics of GitHub releases',
					'parent' => '0',
					'type' => 3,
					'dataset' => 0,
					'link' => '{"dataSourceType":"","data":"release","limit":"10","timestamp":"false","filter":"","showAssets":"false","token":""}',
					'visualization' => 'ct',
					'chart' => 'column',
					'dimension1' => 'Version',
					'dimension2' => 'Version',
					'value' => 'false',
				],
				'options' => [
					'chartoptions' => '{"__analytics_gui":{"version":2,"model":"kpiModel","doughnutLabelStyle":"percentage"}}',
					'dataoptions' => '[]',
					'filteroptions' => '{}',
					'tableoptions' => '{"order":[[0,"desc"]]}',
				],
			],
		];
	}

	/**
	 * Read the Data
	 * @param $option
	 * @return array available options of the data source
	 */
	public function readData($option): array {
		$data = array();
		$header = array();
		$cache = $this->getCacheMetadata($option);

		if ($cache['notModified'] === true) {
			return [
				'header' => [],
				'dimensions' => [],
				'data' => [],
				'rawdata' => null,
				'error' => 0,
				'cache' => $cache,
			];
		}

		if (!isset($option['data']) || $option['data'] === 'release') {
			$url = 'https://api.github.com/repos/' . $option['user'] . '/' . $option['repository'] . '/releases';
			$curlResult = $this->getCurlData($url, $option);
			$http_code = $curlResult['http_code'];
			// Check for HTTP error code
			if ($http_code < 200 || $http_code >= 300) {
				return $this->buildHttpErrorResult($http_code, $curlResult, $cache);
			}
			$i = 0;
			if (isset($option['filter'])) {
				$extensions = explode(',', $option['filter']); // ['gz', 'pkg', 'tbz', 'msi', 'AppImage']
			} else {
				$extensions = [''];
			}

			foreach ($curlResult['data'] as $item) {
				foreach ($item['assets'] as $asset) {
					$extension = pathinfo($asset['name'], PATHINFO_EXTENSION);
					if (in_array($extension, $extensions) || $extensions === ['']) {
						if (isset($option['limit']) and $option['limit'] !== '') {
							if ($i === (int)$option['limit']) break;
						}
						$nc_value = $asset['download_count'];
						if (isset($option['showAssets']) and $option['showAssets'] === 'true') {
							$data[] = [$item['tag_name'], $asset['name'], $this->floatvalue($nc_value)];
						} else {
							$data[] = [$item['tag_name'], $this->floatvalue($nc_value)];
						}
						$i++;
					}
				}
			}

			$header[] = $this->l10n->t('Version');
			if (isset($option['showAssets']) and $option['showAssets'] === 'true') {
				$header[] = $this->l10n->t('Asset');
			}
			$header[] = $this->l10n->t('Download count');
		} else if ($option['data'] === 'package') {
			$url = $this->buildPackageVersionsUrl($option);
			$curlResult = $this->getCurlRawData($url, $option);
			$http_code = $curlResult['http_code'];

			// Check for HTTP error code
			if ($http_code < 200 || $http_code >= 300) {
				return $this->buildHttpErrorResult($http_code, $curlResult, $cache);
			}

			foreach ($this->parsePackageDownloadRows($curlResult['data'], $option) as $versionRow) {
				$data[] = [
					$versionRow['tag'],
					$versionRow['downloads']
				];
			}

			$header[] = $this->l10n->t('Tag');
			$header[] = $this->l10n->t('Download count');
		} else if ($option['data'] === 'issues') {
			$url = 'https://api.github.com/repos/' . $option['user'] . '/' . $option['repository'];
			$curlResult = $this->getCurlData($url, $option);
			$http_code = $curlResult['http_code'];

			// Check for HTTP error code
			if ($http_code < 200 || $http_code >= 300) {
				return $this->buildHttpErrorResult($http_code, $curlResult, $cache);
			}

			$issuesTotal = $curlResult['data']['open_issues'];

			$url = 'https://api.github.com/repos/' . $option['user'] . '/' . $option['repository'] . '/pulls?per_page=100';
			$curlResult = $this->getCurlData($url, $option);
			$http_code = $curlResult['http_code'];
			// Check for HTTP error code
			if ($http_code < 200 || $http_code >= 300) {
				return $this->buildHttpErrorResult($http_code, $curlResult, $cache);
			}
			$pulls = count($curlResult['data']);

			$issuesCleaned = $issuesTotal - $pulls;

			$data[] = [$this->l10n->t('Issues'), $issuesCleaned];
			$header[] = $this->l10n->t('Type');
			$header[] = $this->l10n->t('Count');
		} else if ($option['data'] === 'pulls') {
			$url = 'https://api.github.com/repos/' . $option['user'] . '/' . $option['repository'] . '/pulls?per_page=100';
			$curlResult = $this->getCurlData($url, $option);
			$http_code = $curlResult['http_code'];
			// Check for HTTP error code
			if ($http_code < 200 || $http_code >= 300) {
				return $this->buildHttpErrorResult($http_code, $curlResult, $cache);
			}
			$data[] = [$this->l10n->t('Pull Requests'), count($curlResult['data'])];
			$header[] = $this->l10n->t('Type');
			$header[] = $this->l10n->t('Count');
		}

		usort($data, function ($a, $b) {
			return strnatcmp($a[0], $b[0]);
		});

		return [
			'header' => $header,
			'dimensions' => array_slice($header, 0, count($header) - 1),
			'data' => $data,
			'rawdata' => $curlResult,
			'error' => 0,
			'cache' => $cache,
		];
	}

	private function getCacheMetadata($option): array {
		$currentCacheKey = 'gh-' . (string)floor(time() / self::CACHE_TTL_SECONDS);
		$clientCacheKey = isset($option['cacheKey']) ? trim((string)$option['cacheKey'], '"') : '';

		return [
			'cacheable' => true,
			'key' => $currentCacheKey,
			'notModified' => ($clientCacheKey !== '' && $clientCacheKey === $currentCacheKey),
		];
	}

	private function buildHttpErrorResult(int $httpCode, array $curlResult, array $cache): array {
		return [
			'header' => [],
			'dimensions' => [],
			'data' => [],
			'rawdata' => $curlResult,
			'error' => $this->getHttpErrorMessage($httpCode, $curlResult['data'] ?? []),
			'cache' => $cache,
		];
	}

	private function getHttpErrorMessage(int $httpCode, array $responseData): string {
		if ($httpCode === 401) {
			return $this->l10n->t('Missing or invalid GitHub access token');
		}
		if ($httpCode === 403) {
			$message = $this->getGithubErrorMessage($responseData);
			if ($message !== '' && stripos($message, 'rate limit') === false) {
				return 'GitHub API error: ' . $message;
			}

			return $this->l10n->t('Rate limit exceeded');
		}
		if ($httpCode === 0) {
			return $this->l10n->t('Report not available');
		}

		return 'HTTP response code: ' . $httpCode;
	}

	private function getGithubErrorMessage(array $responseData): string {
		if (isset($responseData['message']) && is_string($responseData['message'])) {
			return $responseData['message'];
		}

		return '';
	}

	private function buildPackageUrl(array $option): string {
		return 'https://api.github.com/orgs/' . rawurlencode($option['user']) . '/packages/container/' . rawurlencode($option['repository']);
	}

	private function buildPackageVersionsUrl(array $option): string {
		return 'https://github.com/orgs/' . rawurlencode($option['user']) . '/packages/container/' . rawurlencode($option['repository']) . '/versions?filters%5Bversion_type%5D=tagged';
	}

	private function parsePackageDownloadRows(string $html, array $option): array {
		$rows = [];
		$limit = isset($option['limit']) && $option['limit'] !== '' ? (int)$option['limit'] : 0;

		preg_match_all('/<li\b[^>]*class="[^"]*\bBox-row\b[^"]*"[^>]*>(.*?)<\/li>/is', $html, $matches);
		foreach ($matches[1] as $rowHtml) {
			preg_match_all('/<a\b[^>]*class="[^"]*\bLabel\b[^"]*"[^>]*>(.*?)<\/a>/is', $rowHtml, $tagMatches);
			if ($tagMatches[1] === []) {
				continue;
			}

			if (!preg_match('/octicon-download.*?<\/svg>\s*([\d,.]+)/is', $rowHtml, $downloadMatch)) {
				continue;
			}

			$downloads = $this->parseDownloadCount($downloadMatch[1]);
			foreach ($tagMatches[1] as $tagHtml) {
				$tag = trim(html_entity_decode(strip_tags($tagHtml), ENT_QUOTES | ENT_HTML5));
				if ($tag !== '') {
					$rows[] = [
						'tag' => $tag,
						'downloads' => $downloads,
					];
				}

				if ($limit > 0 && count($rows) >= $limit) {
					return $rows;
				}
			}
		}

		return $rows;
	}

	private function parseDownloadCount(string $value): int {
		$digits = preg_replace('/\D+/', '', $value);
		if ($digits === null || $digits === '') {
			return 0;
		}

		return (int)$digits;
	}

	private function getCurlRawData($url, $option) {
		$ch = curl_init();
		$http_code = 0;
		if ($ch !== false) {
			curl_setopt_array($ch, $this->buildCurlOptions($url, $option));
			curl_setopt($ch, CURLOPT_HTTPHEADER, $this->buildHtmlRequestHeaders($option));
			$curlResult = curl_exec($ch);
			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);
		} else {
			$curlResult = '';
		}
		return ['data' => (string)$curlResult, 'http_code' => $http_code];
	}

	private function buildHtmlRequestHeaders(array $option): array {
		$headers = [
			'Accept: text/html',
			'User-Agent: Analytics for Nextcloud',
		];

		if (isset($option['token']) && $option['token'] !== '') {
			$headers[] = 'Authorization: token ' . $option['token'];
		}

		return $headers;
	}

	private function getCurlData($url, $option) {
		$ch = curl_init();
		$http_code = 0;
		if ($ch !== false) {
			curl_setopt_array($ch, $this->buildCurlOptions($url, $option));
			$curlResult = curl_exec($ch);
			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);
		} else {
			$curlResult = '';
		}
		$curlResult = json_decode($curlResult, true);
		return ['data' => $curlResult, 'http_code' => $http_code];
	}

	private function buildCurlOptions($url, $option): array {
		$options = [
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_HEADER => false,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_URL => $url,
			CURLOPT_REFERER => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13',
		];

		if (isset($option['token']) && $option['token'] !== '') {
			$options[CURLOPT_HTTPHEADER] = [
				'Authorization: token ' . $option['token'],
				'User-Agent: Analytics for Nextcloud',
				'Accept: application/vnd.github.v3+json'
			];
		}

		return $options;
	}

	private function floatvalue($val) {
		$val = str_replace(",", ".", $val);
		$val = preg_replace('/\.(?=.*\.)/', '', $val);
		$val = preg_replace('/[^0-9-.]+/', '', $val);
		if (is_numeric($val)) {
			return floatval($val);
		} else {
			return false;
		}
	}
}
