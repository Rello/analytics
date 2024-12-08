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

class Github implements IDatasource
{
    private LoggerInterface $logger;
    private IL10N $l10n;

    public function __construct(
        IL10N $l10n,
        LoggerInterface $logger
    )
    {
        $this->l10n = $l10n;
        $this->logger = $logger;
    }

    /**
     * @return string Display Name of the datasource
     */
    public function getName(): string
    {
        return 'GitHub';
    }

    /**
     * @return int digit unique datasource id
     */
    public function getId(): int
    {
        return 3;
    }

    /**
     * @return array available options of the datasource
     */
    public function getTemplate(): array
    {
        $template = array();
        $template[] = ['id' => 'user', 'name' => 'GitHub Username', 'placeholder' => 'GitHub user'];
        $template[] = ['id' => 'repository', 'name' => 'Repository', 'placeholder' => 'GitHub repository'];
		$template[] = ['id' => 'data', 'name' => 'Releases, Issues or PRs', 'placeholder' => 'release-' . $this->l10n->t('Releases').'/issues-'.$this->l10n->t('Issues').'/pulls-'.$this->l10n->t('Pull Requests'), 'type' => 'tf'];
        $template[] = ['id' => 'limit', 'name' => $this->l10n->t('Limit'), 'placeholder' => $this->l10n->t('Number of rows'), 'type' => 'number'];
        $template[] = ['id' => 'timestamp', 'name' => $this->l10n->t('Timestamp of data load'), 'placeholder' => 'false-' . $this->l10n->t('No').'/true-'.$this->l10n->t('Yes'), 'type' => 'tf'];
        return $template;
    }

    /**
     * Read the Data
     * @param $option
     * @return array available options of the data source
     */
    public function readData($option): array
    {
        $http_code = '';
		// old. no one should use it by now
        // if (isset($option['link'])) $string = 'https://api.github.com/repos/' . $option['link'] . '/releases';
        // else $string = 'https://api.github.com/repos/' . $option['user'] . '/' . $option['repository'] . '/releases';

		$data = array();
		$header = array();
		$http_code = 0;

		if ($option['data'] === 'issues') {
			$url = 'https://api.github.com/repos/' . $option['user'] . '/' . $option['repository'];
			$curlResult = $this->getCurlData($url);
			$issuesTotal = $curlResult['data']['open_issues'];

			$url = 'https://api.github.com/repos/' . $option['user'] . '/' . $option['repository'] . '/pulls?per_page=100';
			$curlResult = $this->getCurlData($url);
			$http_code = $curlResult['http_code'];
			$pulls = count($curlResult['data']);

			$issuesCleaned = $issuesTotal - $pulls;

			$data[] = [$this->l10n->t('Issues'), $issuesCleaned];

		} else if ($option['data'] === 'pulls') {
			$url = 'https://api.github.com/repos/' . $option['user'] . '/' . $option['repository'] . '/pulls?per_page=100';
			$curlResult = $this->getCurlData($url);
			$http_code = $curlResult['http_code'];
			$data[] = [$this->l10n->t('Pull Requests'), count($curlResult['data'])];
		} else {
			$url = 'https://api.github.com/repos/' . $option['user'] . '/' . $option['repository'] . '/releases';
			$curlResult = $this->getCurlData($url);
			$http_code = $curlResult['http_code'];
			$i = 0;
			foreach ($curlResult['data'] as $item) {
				if (isset($option['limit']) and $option['limit'] !== '') {
					if ($i === (int)$option['limit']) break;
				}
				$nc_value = 0;
				foreach ($item['assets'] as $asset) {
					if (str_ends_with($asset['name'], 'gz')) $nc_value = $asset['download_count'];
				}
				$data[] = [$item['tag_name'], $this->floatvalue($nc_value)];
				$i++;
			}
			$header[0] = $this->l10n->t('Version');
			$header[1] = $this->l10n->t('Download count');
		}

        usort($data, function ($a, $b) {
            return strnatcmp($a[0], $b[0]);
        });

        return [
            'header' => $header,
            'dimensions' => array_slice($header, 0, count($header) - 1),
            'data' => $data,
            'rawdata' => $curlResult,
            'error' => ($http_code>=200 && $http_code<300) ? 0 : 'HTTP response code: '.$http_code,
        ];
    }

	private	function getCurlData($url) {
		$ch = curl_init();
		if ($ch !== false) {
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt($ch, CURLOPT_HEADER, false);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_REFERER, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
			$curlResult = curl_exec($ch);
			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);
		} else {
			$curlResult = '';
		}
		$curlResult = json_decode($curlResult, true);
		return ['data' => $curlResult, 'http_code' => $http_code];
	}

    private function floatvalue($val)
    {
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