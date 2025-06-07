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

class Regex implements IDatasource
{
    private LoggerInterface $logger;
    private IL10N $l10n;

    public function __construct(
        IL10N           $l10n,
        LoggerInterface $logger
    )
    {
        $this->l10n = $l10n;
        $this->logger = $logger;
    }

    /**
     * @return string Display Name of the data source
     */
    public function getName(): string
    {
        return $this->l10n->t('HTML grabber');
    }

    /**
     * @return int digit unique data source id
     */
    public function getId(): int
    {
        return 5;
    }

    /**
     * @return array available options of the data source
     */
    public function getTemplate(): array
    {
        $template = array();
        $template[] = ['id' => 'url', 'name' => 'URL', 'placeholder' => 'url'];
        $template[] = ['id' => 'name', 'name' => 'Data series description', 'placeholder' => 'optional'];
        $template[] = ['id' => 'regex', 'name' => $this->l10n->t('valid regex'), 'placeholder' => '//'];
        $template[] = ['id' => 'limit', 'name' => $this->l10n->t('Limit'), 'placeholder' => $this->l10n->t('Number of rows'), 'type' => 'number'];
        $template[] = ['id' => 'timestamp', 'name' => $this->l10n->t('Timestamp of data load'), 'placeholder' => 'true-' . $this->l10n->t('Yes') . '/false-' . $this->l10n->t('No'), 'type' => 'tf'];
        return $template;
    }

    /**
     * Read the Data
     * @param $option
     * @return array available options of the data source
     */
	public function readData($option): array
	{
		$regex = isset($option['regex']) ? htmlspecialchars_decode($option['regex'], ENT_NOQUOTES) : '';
		$url = isset($option['url']) ? htmlspecialchars_decode($option['url'], ENT_NOQUOTES) : '';

		// Fetch HTML content using cURL
		[$curlResult, $httpCode] = $this->fetchUrlContent($url);

		$header = ['', 'Dimension2', 'Count'];

		// Early return on HTTP error
		if ($httpCode < 200 || $httpCode >= 300) {
			return [
				'header' => $header,
				'dimensions' => ['', 'Dimension2'],
				'data' => [],
				'error' => 'HTTP response code: ' . $httpCode,
				'rawData' => $curlResult,
				'URL' => $url,
			];
		}

		// Apply regex and build data
		preg_match_all($regex, $curlResult, $matches);

		$dimensions = $matches['dimension'] ?? [];
		$values = $matches['value'] ?? [];
		$limit = isset($option['limit']) && (int)$option['limit'] > 0 ? (int)$option['limit'] : count($dimensions);

		// Use array_map for efficient data construction
		$data = [];
		for ($i = 0; $i < min($limit, count($dimensions), count($values)); $i++) {
			$data[] = [
				$option['name'] ?? '',
				$dimensions[$i],
				$values[$i]
			];
		}

		return [
			'header' => $header,
			'dimensions' => array_slice($header, 0, count($header) - 1),
			'data' => $data,
			'error' => 0,
			'rawData' => $curlResult,
			'URL' => $url,
		];
	}

	/**
	 * Fetches the content of a URL using cURL.
	 * @param string $url
	 * @return array [string $content, int $httpCode]
	 */
	private function fetchUrlContent(string $url): array
	{
		$ch = curl_init();
		if ($ch === false) {
			return ['', 0];
		}

		curl_setopt_array($ch, [
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_HEADER => false,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_URL => $url,
			CURLOPT_REFERER => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13'
		]);

		$result = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		return [$result !== false ? $result : '', $httpCode];
	}
}
