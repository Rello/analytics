<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2025 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Datasource;

use OCP\IL10N;
use Psr\Log\LoggerInterface;

class GithubCommunitySla implements IDatasource {
    private LoggerInterface $logger;
    private IL10N $l10n;

    /**
     * GitHub usernames of employees to exclude
     * @var array<int,string>
     */
    protected array $excludedAuthors = [
        'alice',
        'bob',
    ];

    /**
     * Repositories to analyse in owner/repo format
     * @var array<int,string>
     */
    protected array $repositories = [
        'nextcloud/server',
        'nextcloud/analytics',
    ];

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
        return 'GitHub Community SLA';
    }

    /**
     * @return int digit unique data source id
     */
    public function getId(): int {
        return 8;
    }

    /**
     * @return array available options of the data source
     */
    public function getTemplate(): array {
        $template = [];
        $template[] = [
            'id' => 'token',
            'name' => $this->l10n->t('Personal access token'),
            'placeholder' => $this->l10n->t('optional')
        ];
        $template[] = [
            'id' => 'days',
            'name' => $this->l10n->t('Updated since (days)'),
            'placeholder' => '30'
        ];
        return $template;
    }

    /**
     * Read the Data
     * @param $option
     * @return array available options of the data source
     */
    public function readData($option): array {
        $header = [
            $this->l10n->t('Repository'),
            $this->l10n->t('Type'),
            $this->l10n->t('Number'),
            $this->l10n->t('Created'),
            $this->l10n->t('Completed'),
            $this->l10n->t('Days'),
            $this->l10n->t('SLA met')
        ];
        $data = [];

        $daysFilter = isset($option['days']) && (int)$option['days'] > 0 ? (int)$option['days'] : 30;
        $sinceDate = date(DATE_ATOM, time() - ($daysFilter * 86400));

        foreach ($this->repositories as $repo) {
            [$owner, $name] = explode('/', $repo, 2);
            $query = <<<'GRAPHQL'
query($owner: String!, $name: String!) {
  repository(owner: $owner, name: $name) {
    issues(first: 100, orderBy: {field: UPDATED_AT, direction: DESC}, states: [OPEN, CLOSED]) {
      nodes {
        number
        createdAt
        updatedAt
        author { login }
        timelineItems(first: 100, itemTypes: [LABELED_EVENT, UNLABELED_EVENT]) {
          nodes {
            __typename
            ... on LabeledEvent { createdAt label { name } }
            ... on UnlabeledEvent { createdAt label { name } }
          }
        }
      }
    }
    pullRequests(first: 100, orderBy: {field: UPDATED_AT, direction: DESC}, states: [OPEN, MERGED, CLOSED]) {
      nodes {
        number
        createdAt
        mergedAt
        updatedAt
        author { login }
      }
    }
  }
}
GRAPHQL;

            $curlResult = $this->getGraphqlData($query, ['owner' => $owner, 'name' => $name], $option);
            if ($curlResult['http_code'] < 200 || $curlResult['http_code'] >= 300 || isset($curlResult['data']['errors'])) {
                return [
                    'header' => [],
                    'dimensions' => [],
                    'data' => $curlResult['http_code'] === 403 ? 'Rate limit exceeded' : [],
                    'rawdata' => $curlResult,
                    'error' => 'HTTP response code: ' . $curlResult['http_code'],
                ];
            }

            $repoData = $curlResult['data']['data']['repository'];

            foreach ($repoData['issues']['nodes'] as $issue) {
                if ($issue['updatedAt'] < $sinceDate) {
                    continue;
                }
                if (in_array($issue['author']['login'], $this->excludedAuthors, true)) {
                    continue;
                }
                $triagedAt = '';
                foreach ($issue['timelineItems']['nodes'] as $event) {
                    if (
                        ($event['__typename'] === 'UnlabeledEvent' && isset($event['label']['name']) && $event['label']['name'] === '0. Needs triage') ||
                        ($event['__typename'] === 'LabeledEvent' && isset($event['label']['name']) && $event['label']['name'] === '1. to develop')
                    ) {
                        $triagedAt = $event['createdAt'];
                        break;
                    }
                }
                $days = $this->daysBetween($issue['createdAt'], $triagedAt ?: date(DATE_ATOM));
                $slaMet = $triagedAt !== '' && $days <= 14;
                $data[] = [$repo, 'issue', (int)$issue['number'], $issue['createdAt'], $triagedAt, $days, $slaMet ? 1 : 0];
            }

            foreach ($repoData['pullRequests']['nodes'] as $pr) {
                if ($pr['updatedAt'] < $sinceDate) {
                    continue;
                }
                if (in_array($pr['author']['login'], $this->excludedAuthors, true)) {
                    continue;
                }
                $mergedAt = $pr['mergedAt'] ?? '';
                $days = $this->daysBetween($pr['createdAt'], $mergedAt ?: date(DATE_ATOM));
                $slaMet = $mergedAt !== '' && $days <= 14;
                $data[] = [$repo, 'pr', (int)$pr['number'], $pr['createdAt'], $mergedAt, $days, $slaMet ? 1 : 0];
            }
        }

        return [
            'header' => $header,
            'dimensions' => array_slice($header, 0, count($header) - 1),
            'data' => $data,
            'rawdata' => [],
            'error' => 0,
        ];
    }

    protected function getGraphqlData(string $query, array $variables, array $option): array {
        $ch = curl_init('https://api.github.com/graphql');
        if ($ch !== false) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['query' => $query, 'variables' => $variables]));
            $headers = [
                'Content-Type: application/json',
                'User-Agent: AnalyticsApp'
            ];
            if (isset($option['token']) && $option['token'] !== '') {
                $headers[] = 'Authorization: bearer ' . $option['token'];
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $curlResult = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        } else {
            $curlResult = '';
            $http_code = 500;
        }
        $curlResult = json_decode($curlResult, true);
        return ['data' => $curlResult, 'http_code' => $http_code];
    }

    private function daysBetween(string $from, string $to): int {
        $start = new \DateTime($from);
        $end = new \DateTime($to);
        return $start->diff($end)->days;
    }
}
