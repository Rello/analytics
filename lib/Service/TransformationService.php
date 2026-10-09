<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Analytics\Service;

/**
 * Executes a versioned transformation over complete, unaggregated source rows.
 * The input and output use the same header/data/columnRefs contract as reports.
 */
class TransformationService {
	public function execute(array $source, array $definition, array $options = []): array {
		if (($definition['version'] ?? null) !== 1) {
			throw new \InvalidArgumentException('Unsupported transformation version.');
		}
		if (($source['truncated'] ?? false) === true || ($source['queryProcessing']['pagination'] ?? false) === true) {
			throw new \InvalidArgumentException('Calculations require the complete source result before pagination.');
		}
		$header = array_values($source['header'] ?? []);
		$refs = array_values($source['columnRefs'] ?? array_map(static fn (int $i): string => 'source:' . $i, array_keys($header)));
		if (count($header) !== count($refs) || count($refs) !== count(array_unique($refs))) {
			throw new \InvalidArgumentException('The source column schema is invalid.');
		}
		$columns = [];
		$measureNames = $source['keyFigures'] ?? [];
		foreach ($refs as $index => $ref) {
			$descriptor = $source['columns'][$index] ?? $source['sourceColumns'][$index] ?? [];
			$role = $descriptor['role'] ?? (in_array($header[$index], $measureNames, true) ? 'measure' : 'dimension');
			$columns[$ref] = [
				...$descriptor,
				'ref' => $ref,
				'name' => (string)$header[$index],
				'role' => $role,
				'aggregation' => $descriptor['defaultAggregation'] ?? 'sum',
				'type' => $descriptor['type'] ?? ($role === 'measure' ? 'decimal' : 'text'),
			];
		}
		foreach (($definition['aggregations'] ?? []) as $ref => $aggregation) {
			if (!isset($columns[$ref]) || $columns[$ref]['role'] !== 'measure'
				|| !in_array($aggregation, ['none', 'sum', 'avg', 'min', 'max', 'count', 'count_distinct'], true)) {
				throw new \InvalidArgumentException('A source measure has an invalid aggregation.');
			}
			$columns[$ref]['aggregation'] = $aggregation;
		}
		$sourceRefs = $refs;
		$calculations = $this->prepareCalculations($definition['calculations'] ?? [], $columns);
		$rows = [];
		foreach ($source['data'] ?? [] as $input) {
			if (!is_array($input)) {
				continue;
			}
			$row = [];
			foreach ($sourceRefs as $index => $ref) {
				$row[$ref] = $input[$index] ?? null;
			}
			foreach ($calculations as $calc) {
				if ($calc['phase'] === 'before') {
					$row[$calc['ref']] = $this->evaluate($calc['rpn'], $row);
				}
			}
			$rows[] = $row;
		}

		$rowMeasureRefs = array_values(array_filter($sourceRefs, static fn (string $ref): bool => $columns[$ref]['role'] === 'measure'));
		foreach ($calculations as $calc) {
			if ($calc['phase'] === 'before') {
				$rowMeasureRefs[] = $calc['ref'];
			}
		}
		$groupingMeasures = array_values(array_filter($rowMeasureRefs, static fn (string $ref): bool => $columns[$ref]['aggregation'] === 'none'));
		$aggregate = ($options['aggregate'] ?? true) !== false && count($groupingMeasures) < count($rowMeasureRefs);
		$hiddenDimensions = array_map(static fn (string $ref): string => ctype_digit($ref) ? 'source:' . $ref : $ref,
			array_map('strval', array_keys($options['drilldown'] ?? [])));
		$dimensions = array_values(array_filter($sourceRefs, static fn (string $ref): bool =>
			$columns[$ref]['role'] === 'dimension' && !in_array($ref, $hiddenDimensions, true)));
		if (($source['queryProcessing']['aggregation'] ?? false) === true) {
			if (!$aggregate || $groupingMeasures !== [] || count($dimensions) !== count(array_filter($sourceRefs, static fn (string $ref): bool => $columns[$ref]['role'] === 'dimension'))
				|| count(array_filter($calculations, static fn (array $calc): bool => $calc['phase'] === 'before')) > 0) {
				throw new \InvalidArgumentException('The source was aggregated before the required row calculations or grouping.');
			}
		}
		foreach ($calculations as $calc) {
			if ($aggregate && $calc['phase'] === 'after') {
				foreach ($calc['dependencies'] as $dependency) {
					if ($columns[$dependency]['role'] === 'dimension' && !in_array($dependency, $dimensions, true)) {
						throw new \InvalidArgumentException('An after-aggregation measure uses a removed dimension.');
					}
				}
			}
		}
		if ($aggregate && !($source['queryProcessing']['aggregation'] ?? false)) {
			$rows = $this->aggregate($rows, $columns, array_merge($dimensions, $groupingMeasures));
		}
		foreach ($rows as &$row) {
			foreach ($calculations as $calc) {
				if ($calc['phase'] === 'after') {
					$row[$calc['ref']] = $this->evaluate($calc['rpn'], $row);
				}
			}
		}
		unset($row);

		$sort = $definition['sort'] ?? [];
		if (!is_array($sort)) {
			throw new \InvalidArgumentException('The sort definition is invalid.');
		}
		foreach ($sort as $item) {
			$ref = (string)($item['column'] ?? '');
			if (!isset($columns[$ref]) || !in_array(strtoupper((string)($item['direction'] ?? '')), ['ASC', 'DESC'], true)) {
				throw new \InvalidArgumentException('The sort column or direction is invalid.');
			}
			if ($aggregate && $columns[$ref]['role'] === 'dimension' && !in_array($ref, $dimensions, true)) {
				throw new \InvalidArgumentException('A removed dimension cannot be used to sort aggregated rows.');
			}
		}
		if ($sort !== []) {
			usort($rows, static function (array $a, array $b) use ($sort): int {
				foreach ($sort as $item) {
					$ref = $item['column'];
					$left = $a[$ref] ?? null;
					$right = $b[$ref] ?? null;
					$comparison = is_numeric($left) && is_numeric($right) ? (float)$left <=> (float)$right : (string)$left <=> (string)$right;
					if ($comparison !== 0) {
						return strtoupper($item['direction']) === 'DESC' ? -$comparison : $comparison;
					}
				}
				return 0;
			});
		}
		$limit = isset($definition['limit']) ? (int)$definition['limit'] : 0;
		if ($limit > 0) {
			$rows = array_slice($rows, 0, min($limit, 10000));
		}
		$hidden = $definition['hidden'] ?? [];
		if (!is_array($hidden)) {
			throw new \InvalidArgumentException('The hidden columns definition is invalid.');
		}
		foreach ($hidden as $ref) {
			if (!is_string($ref) || !isset($columns[$ref])) {
				throw new \InvalidArgumentException('A hidden column is no longer available.');
			}
		}
		$outputRefs = array_values(array_filter(array_keys($columns), static fn (string $ref): bool =>
			!in_array($ref, $hidden, true) && ($columns[$ref]['role'] !== 'dimension' || in_array($ref, $dimensions, true))));
		if ($outputRefs === []) {
			throw new \InvalidArgumentException('At least one output column is required.');
		}
		$source['header'] = array_map(static fn (string $ref): string => $columns[$ref]['name'], $outputRefs);
		$source['columnRefs'] = $outputRefs;
		$source['columns'] = array_map(static fn (string $ref): array => $columns[$ref], $outputRefs);
		$source['sourceColumns'] = $source['sourceColumns'] ?? array_values($columns);
		$source['keyFigures'] = array_values(array_map(static fn (string $ref): string => $columns[$ref]['name'],
			array_filter($outputRefs, static fn (string $ref): bool => $columns[$ref]['role'] === 'measure')));
		$source['data'] = array_map(static fn (array $row): array =>
			array_map(static fn (string $ref): mixed => $row[$ref] ?? null, $outputRefs), $rows);
		$source['queryProcessing'] = [
			'backendProcessed' => true,
			'aggregation' => $aggregate,
			'sorting' => $sort !== [],
			'pagination' => $limit > 0,
		];
		return $source;
	}

	private function prepareCalculations(mixed $definitions, array &$columns): array {
		if (!is_array($definitions) || count($definitions) > 50) {
			throw new \InvalidArgumentException('The calculated measures definition is invalid.');
		}
		$pending = [];
		foreach ($definitions as $definition) {
			$ref = (string)($definition['id'] ?? '');
			$name = trim((string)($definition['name'] ?? ''));
			$phase = (string)($definition['phase'] ?? '');
			$aggregation = strtolower((string)($definition['aggregation'] ?? 'sum'));
			if (!preg_match('/^calc:[A-Za-z0-9_-]+$/D', $ref) || isset($columns[$ref]) || isset($pending[$ref])
				|| $name === '' || !in_array($phase, ['before', 'after'], true)
				|| !in_array($aggregation, ['none', 'sum', 'avg', 'min', 'max', 'count', 'count_distinct'], true)) {
				throw new \InvalidArgumentException('A calculated measure has an invalid identifier, name, phase, or aggregation.');
			}
			$expression = (string)($definition['expression'] ?? '');
			if (strlen($expression) > 512) throw new \InvalidArgumentException('A formula is too long.');
			$rpn = $this->compile($expression);
			$dependencies = array_values(array_unique(array_filter($rpn, static fn (string $token): bool => str_starts_with($token, '{'))));
			$pending[$ref] = ['ref' => $ref, 'phase' => $phase, 'aggregation' => $aggregation, 'rpn' => $rpn, 'dependencies' =>
				array_map(static fn (string $token): string => substr($token, 1, -1), $dependencies)];
			$columns[$ref] = ['ref' => $ref, 'name' => $name, 'role' => 'measure', 'aggregation' => $aggregation, 'type' => 'decimal'];
		}
		$ordered = [];
		$visiting = [];
		$visit = function (string $ref) use (&$visit, &$ordered, &$visiting, $pending, $columns): void {
			if (isset($ordered[$ref])) {
				return;
			}
			if (isset($visiting[$ref])) {
				throw new \InvalidArgumentException('Calculated measures contain a circular reference.');
			}
			$visiting[$ref] = true;
			$calc = $pending[$ref];
			foreach ($calc['dependencies'] as $dependency) {
				if (!isset($columns[$dependency])) {
					throw new \InvalidArgumentException('A calculated measure references an unavailable column.');
				}
				if (isset($pending[$dependency])) {
					if ($calc['phase'] === 'before' && $pending[$dependency]['phase'] === 'after') {
						throw new \InvalidArgumentException('A before-aggregation measure cannot use an after-aggregation measure.');
					}
					$visit($dependency);
				}
			}
			unset($visiting[$ref]);
			$ordered[$ref] = $calc;
		};
		foreach (array_keys($pending) as $ref) {
			$visit($ref);
		}
		return array_values($ordered);
	}

	private function aggregate(array $rows, array $columns, array $dimensions): array {
		$groups = [];
		foreach ($rows as $row) {
			$key = json_encode(array_map(static fn (string $ref): mixed => $row[$ref] ?? null, $dimensions), JSON_THROW_ON_ERROR);
			if (!isset($groups[$key])) {
				$groups[$key] = ['values' => array_intersect_key($row, array_flip($dimensions)), 'state' => []];
			}
			foreach ($columns as $ref => $column) {
				if ($column['role'] !== 'measure' || $column['aggregation'] === 'none' || !array_key_exists($ref, $row)) {
					continue;
				}
				$value = $row[$ref];
				if ($value === null || $value === '') {
					continue;
				}
				$state = &$groups[$key]['state'][$ref];
				$method = $column['aggregation'];
				if ($method === 'sum' || $method === 'avg') {
					if (is_numeric($value)) {
						$state['sum'] = ($state['sum'] ?? 0.0) + (float)$value;
						$state['numericCount'] = ($state['numericCount'] ?? 0) + 1;
					} elseif ($method === 'sum') {
						$state['lastText'] = $value;
					}
				} elseif ($method === 'min') {
					$state['value'] = isset($state['value']) ? min($state['value'], $value) : $value;
				} elseif ($method === 'max') {
					$state['value'] = isset($state['value']) ? max($state['value'], $value) : $value;
				} elseif ($method === 'count_distinct') {
					$state['distinct'][json_encode($value, JSON_THROW_ON_ERROR)] = true;
				}
				$state['count'] = ($state['count'] ?? 0) + 1;
				unset($state);
			}
		}
		$result = [];
		foreach ($groups as $group) {
			$row = $group['values'];
			foreach ($columns as $ref => $column) {
				if ($column['role'] !== 'measure' || $column['aggregation'] === 'none') {
					continue;
				}
				$state = $group['state'][$ref] ?? [];
				$count = $state['count'] ?? 0;
				$row[$ref] = match ($column['aggregation']) {
					'avg' => ($state['numericCount'] ?? 0) ? $state['sum'] / $state['numericCount'] : null,
					'count' => $count,
					'count_distinct' => count($state['distinct'] ?? []),
					'min', 'max' => $state['value'] ?? null,
					default => $state['sum'] ?? $state['lastText'] ?? null,
				};
			}
			$result[] = $row;
		}
		return $result;
	}

	private function compile(string $expression): array {
		$tokens = [];
		$offset = 0;
		while ($offset < strlen($expression)) {
			if (preg_match('/\G\s+/A', $expression, $match, 0, $offset)) {
				$offset += strlen($match[0]);
				continue;
			}
			if (!preg_match('/\G(\{[A-Za-z0-9_:-]+\}|(?:\d+(?:\.\d*)?|\.\d+)|[()+*\/-])/A', $expression, $match, 0, $offset)) {
				throw new \InvalidArgumentException('The formula contains an unsupported token.');
			}
			$tokens[] = $match[1];
			$offset += strlen($match[0]);
		}
		if ($tokens === []) {
			throw new \InvalidArgumentException('A formula is required.');
		}
		$output = [];
		$operators = [];
		$precedence = ['+' => 1, '-' => 1, '*' => 2, '/' => 2, 'neg' => 3];
		$expectOperand = true;
		foreach ($tokens as $token) {
			if ($token === '(') {
				if (!$expectOperand) throw new \InvalidArgumentException('Invalid formula parentheses.');
				$operators[] = $token;
			} elseif ($token === ')') {
				if ($expectOperand) throw new \InvalidArgumentException('Invalid formula parentheses.');
				while ($operators !== [] && end($operators) !== '(') $output[] = array_pop($operators);
				if ($operators === []) throw new \InvalidArgumentException('Unmatched formula parenthesis.');
				array_pop($operators);
				$expectOperand = false;
			} elseif (isset($precedence[$token])) {
				if ($expectOperand && $token !== '-') throw new \InvalidArgumentException('Invalid formula operator.');
				if ($expectOperand) $token = 'neg';
				while ($operators !== [] && end($operators) !== '(' && $precedence[end($operators)] >= $precedence[$token] && $token !== 'neg') {
					$output[] = array_pop($operators);
				}
				$operators[] = $token;
				$expectOperand = true;
			} else {
				if (!$expectOperand) throw new \InvalidArgumentException('Missing formula operator.');
				$output[] = $token;
				$expectOperand = false;
			}
		}
		if ($expectOperand) throw new \InvalidArgumentException('Incomplete formula.');
		while ($operators !== []) {
			$operator = array_pop($operators);
			if ($operator === '(') throw new \InvalidArgumentException('Unmatched formula parenthesis.');
			$output[] = $operator;
		}
		return $output;
	}

	private function evaluate(array $rpn, array $row): ?float {
		$stack = [];
		foreach ($rpn as $token) {
			if (str_starts_with($token, '{')) {
				$value = $row[substr($token, 1, -1)] ?? null;
				$stack[] = is_numeric($value) ? (float)$value : null;
			} elseif (is_numeric($token)) {
				$stack[] = (float)$token;
			} elseif ($token === 'neg') {
				$value = array_pop($stack);
				$stack[] = $value === null ? null : -$value;
			} else {
				$right = array_pop($stack);
				$left = array_pop($stack);
				$stack[] = $left === null || $right === null || ($token === '/' && $right == 0.0) ? null : match ($token) {
					'+' => $left + $right,
					'-' => $left - $right,
					'*' => $left * $right,
					'/' => $left / $right,
				};
			}
		}
		$value = $stack[0] ?? null;
		return $value !== null && is_finite($value) ? $value : null;
	}
}
