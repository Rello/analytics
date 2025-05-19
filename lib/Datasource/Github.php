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

class Github implements IDatasource {
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
		$template[] = ['id' => 'repository', 'name' => 'Repository', 'placeholder' => 'GitHub repository'];
		$template[] = [
			'id' => 'data',
			'name' => 'Releases, Issues or PRs',
			'placeholder' => 'release-' . $this->l10n->t('Releases') . '/issues-' . $this->l10n->t('Issues') . '/pulls-' . $this->l10n->t('Pull Requests'),
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
	 * Read the Data
	 * @param $option
	 * @return array available options of the data source
	 */
	public function readData($option): array {
		$data = array();
		$header = array();

		if (!isset($option['data']) || $option['data'] === 'release') {
			$url = 'https://api.github.com/repos/' . $option['user'] . '/' . $option['repository'] . '/releases';
			$curlResult = $this->getCurlData($url, $option);
			$http_code = $curlResult['http_code'];
			// Check for HTTP error code
			if ($http_code < 200 || $http_code >= 300) {
				return [
					'header' => [],
					'dimensions' => [],
					'data' => $http_code === 403 ? 'Rate limit exceeded' : [],
					'rawdata' => $curlResult,
					'error' => 'HTTP response code: ' . $http_code,
				];
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
		} else if ($option['data'] === 'issues') {
			$url = 'https://api.github.com/repos/' . $option['user'] . '/' . $option['repository'];
			$curlResult = $this->getCurlData($url, $option);
			$http_code = $curlResult['http_code'];

			// Check for HTTP error code
			if ($http_code < 200 || $http_code >= 300) {
				return [
					'header' => [],
					'dimensions' => [],
					'data' => $http_code === 403 ? 'Rate limit exceeded' : [],
					'rawdata' => $curlResult,
					'error' => 'HTTP response code: ' . $http_code,
				];
			}

			$issuesTotal = $curlResult['data']['open_issues'];

			$url = 'https://api.github.com/repos/' . $option['user'] . '/' . $option['repository'] . '/pulls?per_page=100';
			$curlResult = $this->getCurlData($url, $option);
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
				return [
					'header' => [],
					'dimensions' => [],
					'data' => $http_code === 403 ? 'Rate limit exceeded' : [],
					'rawdata' => $curlResult,
					'error' => 'HTTP response code: ' . $http_code,
				];
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
		];
	}

	private function getCurlData($url, $option) {
		$ch = curl_init();
		if ($ch !== false) {
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_HEADER, false);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_REFERER, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
			if (isset($option['token']) && $option['token'] !== '') {
				$headers = [
					'Authorization: token ' . $option['token'],
					'User-Agent: YourAppName',
					'Accept: application/vnd.github.v3+json'
				];
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			}
			$curlResult = curl_exec($ch);
			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);
		} else {
			$curlResult = '';
		}
		$curlResult = json_decode($curlResult, true);
		return ['data' => $curlResult, 'http_code' => $http_code];
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