<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Datasource;

use OCP\Files\IRootFolder;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

class LocalJson implements IDatasource {
	private $rootFolder;
	private LoggerInterface $logger;
	private IL10N $l10n;

	public function __construct(
		IL10N           $l10n,
		IRootFolder     $rootFolder,
		LoggerInterface $logger
	) {
		$this->l10n = $l10n;
		$this->rootFolder = $rootFolder;
		$this->logger = $logger;
	}

	/**
         * @return string Display Name of the data source
	 */
	public function getName(): string {
		return $this->l10n->t('Local') . ': JSON';
	}

	/**
         * @return int digit unique data source id
	 */
	public function getId(): int {
		return 2;
	}

	/**
         * @return array available options of the data source
	 */
	public function getTemplate(): array {
		$template = array();
		$template[] = [
			'id' => 'link',
			'name' => $this->l10n->t('File'),
			'placeholder' => $this->l10n->t('File'),
			'type' => 'filePicker'
		];
		$template[] = ['id' => 'path', 'name' => $this->l10n->t('Object path'), 'placeholder' => 'x/y/z'];
		$template[] = [
			'id' => 'timestamp',
			'name' => $this->l10n->t('Timestamp of data load'),
			'placeholder' => 'true-' . $this->l10n->t('Yes') . '/false-' . $this->l10n->t('No'),
			'type' => 'tf'
		];
		return $template;
	}

	/**
	 * Read the Data
	 * @param $option
	 * @return array available options of the data source
	 */
	public function readData($option): array {
		$file = $this->rootFolder->getUserFolder($option['user_id'])->get($option['link']);
		$rawResult = $file->getContent();

		$return = $this->jsonParser($option, $rawResult);
		$return['rawdata'] = $rawResult;
		$return['error'] = 0;

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