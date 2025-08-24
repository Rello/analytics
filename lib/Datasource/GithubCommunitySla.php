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
        return 9;
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
            'id' => 'repo',
            'name' => $this->l10n->t('Repositories'),
            'placeholder' => 'owner/repo1,owner/repo2'
        ];
        $template[] = [
            'id' => 'exclude',
            'name' => $this->l10n->t('Exclude authors'),
            'placeholder' => 'user1,user2'
        ];
        $template[] = [
            'id' => 'sla',
            'name' => $this->l10n->t('SLA days'),
            'placeholder' => '14',
            'type' => 'number'
        ];
        $template[] = [
            'id' => 'days',
            'name' => $this->l10n->t('Updated since (days)'),
            'placeholder' => '30',
            'type' => 'number'
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
            $this->l10n->t('SLA met'),
            $this->l10n->t('Counter')
        ];
        $data = [];

        $repositories = isset($option['repo']) && $option['repo'] !== ''
            ? array_map('trim', explode(',', $option['repo']))
            : $this->repositories;
        $excludedAuthors = isset($option['exclude']) && $option['exclude'] !== ''
            ? array_map('trim', explode(',', $option['exclude']))
            : $this->excludedAuthors;
        $slaDays = isset($option['sla']) && (int)$option['sla'] > 0 ? (int)$option['sla'] : 14;
        $daysFilter = isset($option['days']) && (int)$option['days'] > 0 ? (int)$option['days'] : 30;
        $sinceDate = date(DATE_ATOM, time() - ($daysFilter * 86400));

        foreach ($repositories as $repo) {
            [$owner, $name] = explode('/', $repo, 2);
            $query = <<<'GRAPHQL'
query($owner: String!, $name: String!) {
  repository(owner: $owner, name: $name) {
    issues(first: 100, orderBy: {field: UPDATED_AT, direction: DESC}, states: [OPEN, CLOSED]) {
      nodes {
        number
        createdAt
        updatedAt
        closedAt
        author { login }
        timelineItems(first: 100, itemTypes: [UNLABELED_EVENT]) {
          nodes {
            __typename
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
        closedAt
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
                if (in_array($issue['author']['login'], $excludedAuthors, true)) {
                    continue;
                }
                $completedAt = '';
                foreach ($issue['timelineItems']['nodes'] as $event) {
                    if ($event['__typename'] === 'UnlabeledEvent' && isset($event['label']['name']) && $event['label']['name'] === '0. Needs triage') {
                        $completedAt = $event['createdAt'];
                        break;
                    }
                }
                if ($completedAt === '' && isset($issue['closedAt']) && $issue['closedAt'] !== null) {
                    $completedAt = $issue['closedAt'];
                }
                $days = $this->daysBetween($issue['createdAt'], $completedAt ?: date(DATE_ATOM));
                $slaMet = $days <= $slaDays;
                $data[] = [$repo, 'issue', (int)$issue['number'], $issue['createdAt'], $completedAt, $days, $slaMet ? 1 : 0, 1];
            }

            foreach ($repoData['pullRequests']['nodes'] as $pr) {
                if ($pr['updatedAt'] < $sinceDate) {
                    continue;
                }
                if (in_array($pr['author']['login'], $excludedAuthors, true)) {
                    continue;
                }
                $completedAt = $pr['mergedAt'] ?? $pr['closedAt'] ?? '';
                $days = $this->daysBetween($pr['createdAt'], $completedAt ?: date(DATE_ATOM));
                $slaMet = $days <= $slaDays;
                $data[] = [$repo, 'pr', (int)$pr['number'], $pr['createdAt'], $completedAt, $days, $slaMet ? 1 : 0, 1];
            }
        }

        return [
            'header' => $header,
            'dimensions' => array_slice($header, 0, count($header) - 2),
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
