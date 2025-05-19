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

class ExternalJson implements IDatasource
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
        return $this->l10n->t('External') . ': JSON';
    }

    /**
     * @return int digit unique data source id
     */
    public function getId(): int
    {
        return 6;
    }

    /**
     * @return array available options of the data source
     */
    public function getTemplate(): array
    {
        $template = array();
        $template[] = ['id' => 'url', 'name' => 'URL', 'placeholder' => 'url'];
        $template[] = ['id' => 'method', 'name' => $this->l10n->t('HTTP method'), 'placeholder' => 'GET/POST', 'type' => 'tf'];
        $template[] = ['id' => 'path', 'name' => $this->l10n->t('Object path'), 'placeholder' => 'x/y/z'];
        $template[] = ['id' => 'section', 'name' => $this->l10n->t('More options'), 'type' => 'section'];
        $template[] = ['id' => 'content-type', 'name' => 'Header Content-Type', 'placeholder' => 'application/json'];
        $template[] = ['id' => 'customHeaders', 'name' => 'Custom headers', 'placeholder' => 'key: value,key: value'];
        $template[] = ['id' => 'auth', 'name' => $this->l10n->t('Authentication'), 'placeholder' => 'User:Password'];
        $template[] = ['id' => 'insecure', 'name' => $this->l10n->t('Allow insecure connections'), 'placeholder' => '2-' . $this->l10n->t('No') . '/0-' . $this->l10n->t('Yes'), 'type' => 'tf'];
        $template[] = ['id' => 'body', 'name' => 'Request body', 'placeholder' => ''];
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
        $url = htmlspecialchars_decode($option['url'], ENT_NOQUOTES);
        $auth = $option['auth'];
        $post = $option['method'] === 'POST';
        $contentType = ($option['content-type'] && $option['content-type'] !== '') ? $option['content-type'] : 'application/json';
        $data = array();
        $http_code = '';
        $headers = ($option['customHeaders'] && $option['customHeaders'] !== '') ? explode(",", $option['customHeaders']) : [];
        $headers = array_map('trim', $headers);
        $headers[] = 'OCS-APIRequest: true';
        $headers[] = 'Content-Type: ' . $contentType;
        # VERIFYHOST is used with CURLOPT_SSL_VERIFYHOST: 0 disables verification,
        # 2 enables it. Value 1 is deprecated and should not be used.
        $verifyHost = intval($option['insecure']);

        $ch = curl_init();
        if ($ch !== false) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, $post);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_USERPWD, $auth);
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verifyHost);
            if ($option['body'] && $option['body'] !== '') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $option['body']);
            }
            $rawResult = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        } else {
            $rawResult = '';
        }

		$return = $this->jsonParser($option, $rawResult);
		$return['rawdata'] = $rawResult;
		$return['customHeaders'] = $headers;
		$return['URL'] = $url;
		$return['error'] = ($http_code >= 200 && $http_code < 300) ? 0 : 'HTTP response code: ' . $http_code;

		return $return;
	}

	/**
	 * Parses JSON data based on the provided options and raw input.
	 *
	 * @param array $option
	 * @param string $rawResult
	 * @return array
	 */
	private function jsonParser($option, $rawResult): array {
		$pathString = $option['path'];
		$json = $this->decodeJsonInput($rawResult);

		// Check for array extraction pattern (e.g., {BTC,tmsp,price})
		$arrayFields = $this->extractArrayFields($pathString);
		if (!empty($arrayFields)) {
			// Handle absolute path before array fields (e.g., data/data{from,to,intensity/forecast})
			$json = $this->extractAbsolutePathIfPresent($json, $pathString);

			// Normalize paths and ensure at least 3 columns
			$paths = $this->normalizePaths($arrayFields, 3, 'empty');
			$data = $this->extractRowsFromArray($json, $paths);
		} else {
			// Single value extraction (e.g., data/currentHashrate,data/averageHashrate)
			$paths = array_map('trim', explode(',', $pathString));
			$data = $this->extractSingleValues($json, $paths);
		}

		// Derive header from paths (use last segment for each path)
		$header = array_map([$this, 'getHeaderFromPath'], $paths);

		return [
			'header' => $header,
			'dimensions' => array_slice($header, 0, count($header) - 1),
			'data' => $data,
		];
	}

	/**
	 * Decode JSON input, supporting both standard and line-delimited JSON.
	 */
	private function decodeJsonInput(string $rawResult) {
		$json = json_decode($rawResult, true);
		if ($json === null && trim($rawResult) !== '') {
			$json = [];
			foreach (explode("\n", trim($rawResult)) as $line) {
				$decoded = json_decode($line, true);
				if ($decoded !== null) {
					$json[] = $decoded;
				}
			}
		}
		return $json;
	}

	/**
	 * Extracts fields inside curly braces from the path string.
	 */
	private function extractArrayFields(string $path): array {
		preg_match_all("/(?<={).*(?=})/", $path, $matches);
		return $matches[0] ?? [];
	}

	/**
	 * If an absolute path precedes the array fields, extract the sub-array.
	 */
	private function extractAbsolutePathIfPresent($json, string $path) {
		$firstBracePos = strpos($path, '{');
		if ($firstBracePos !== false && $firstBracePos > 0) {
			$absolutePath = substr($path, 0, $firstBracePos);
			return $this->get_nested_array_value($json, $absolutePath);
		}
		return $json;
	}

	/**
	 * Ensures the paths array has at least $minCount elements, padding with $fill if needed.
	 */
	private function normalizePaths(array $fields, int $minCount, string $fill): array {
		$paths = array_map('trim', explode(',', $fields[0]));
		while (count($paths) < $minCount) {
			array_unshift($paths, $fill);
		}
		return $paths;
	}

	/**
	 * Extracts rows from an array of arrays, using the provided paths.
	 */
	private function extractRowsFromArray($json, array $paths): array {
		$data = [];
		if (is_array($json) && is_array(reset($json))) {
			foreach ($json as $rowArray) {
				$data[] = $this->extractRow($rowArray, $paths);
			}
		} else {
			$data[] = $this->extractRow($json, $paths);
		}
		return $data;
	}

	/**
	 * Extracts a single row's values based on the given paths.
	 */
	private function extractRow($rowArray, array $paths): array {
		$row = [];
		foreach ($paths as $path) {
			$value = $this->get_nested_array_value($rowArray, $path);
			// If value is null, use the path as a placeholder
			if ($value === null) {
				$value = $path;
			} elseif (is_array($value)) {
				$value = implode(', ', array_map('strval', $value));
			}
			$row[] = $value;
		}
		return $row;
	}

	/**
	 * Extracts single values for each path, handling arrays and scalars.
	 */
	private function extractSingleValues($json, array $paths): array {
		$data = [];
		foreach ($paths as $singlePath) {
			$value = $this->get_nested_array_value($json, $singlePath);
			$key = $this->getHeaderFromPath($singlePath);
			if (is_array($value)) {
				foreach ($value as $subKey => $subValue) {
					$data[] = [$key, $subKey, $subValue];
				}
			} else {
				$data[] = ['', $key, $value];
			}
		}
		return $data;
	}

	/**
	 * Gets the last segment of a path separated by '/'.
	 */
	private function getHeaderFromPath(string $path): string {
		$parts = explode('/', $path);
		return end($parts);
	}

	/**
	 * get array object from string
	 *
	 * @NoAdminRequired
	 * @param $array
	 * @param $path
	 * @return array|string|null
	 */
	private function get_nested_array_value($array, $path) {
		$pathParts = explode('/', $path);
		$current = $array;
		foreach ($pathParts as $key) {
			if (!is_array($current) || !array_key_exists($key, $current)) {
				return null;
			}
			$current = $current[$key];
		}
		return $current;
	}
}