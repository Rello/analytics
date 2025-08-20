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

        foreach ($this->repositories as $repo) {
            // Issues
            $issuesUrl = 'https://api.github.com/repos/' . $repo . '/issues?state=all&per_page=100';
            $curlResult = $this->getCurlData($issuesUrl, $option);
            if ($curlResult['http_code'] < 200 || $curlResult['http_code'] >= 300) {
                return [
                    'header' => [],
                    'dimensions' => [],
                    'data' => $curlResult['http_code'] === 403 ? 'Rate limit exceeded' : [],
                    'rawdata' => $curlResult,
                    'error' => 'HTTP response code: ' . $curlResult['http_code'],
                ];
            }

            foreach ($curlResult['data'] as $issue) {
                if (isset($issue['pull_request'])) {
                    // skip pull requests in issue endpoint
                    continue;
                }
                if (in_array($issue['user']['login'], $this->excludedAuthors, true)) {
                    continue;
                }
                $triagedAt = '';
                $eventsUrl = 'https://api.github.com/repos/' . $repo . '/issues/' . $issue['number'] . '/events';
                $eventsCurl = $this->getCurlData($eventsUrl, $option);
                if ($eventsCurl['http_code'] >= 200 && $eventsCurl['http_code'] < 300) {
                    foreach ($eventsCurl['data'] as $event) {
                        if ($event['event'] === 'labeled' && isset($event['label']['name']) && $event['label']['name'] === '1. to develop') {
                            $triagedAt = $event['created_at'];
                            break;
                        }
                    }
                }
                $days = $this->daysBetween($issue['created_at'], $triagedAt ?: date(DATE_ATOM));
                $slaMet = $triagedAt !== '' && $days <= 14;
                $data[] = [$repo, 'issue', (int)$issue['number'], $issue['created_at'], $triagedAt, $days, $slaMet ? 1 : 0];
            }

            // Pull requests
            $pullsUrl = 'https://api.github.com/repos/' . $repo . '/pulls?state=all&per_page=100';
            $pullsCurl = $this->getCurlData($pullsUrl, $option);
            if ($pullsCurl['http_code'] < 200 || $pullsCurl['http_code'] >= 300) {
                return [
                    'header' => [],
                    'dimensions' => [],
                    'data' => $pullsCurl['http_code'] === 403 ? 'Rate limit exceeded' : [],
                    'rawdata' => $pullsCurl,
                    'error' => 'HTTP response code: ' . $pullsCurl['http_code'],
                ];
            }

            foreach ($pullsCurl['data'] as $pr) {
                if (in_array($pr['user']['login'], $this->excludedAuthors, true)) {
                    continue;
                }
                $mergedAt = $pr['merged_at'] ?? '';
                $days = $this->daysBetween($pr['created_at'], $mergedAt ?: date(DATE_ATOM));
                $slaMet = $mergedAt !== '' && $days <= 14;
                $data[] = [$repo, 'pr', (int)$pr['number'], $pr['created_at'], $mergedAt, $days, $slaMet ? 1 : 0];
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

    protected function getCurlData($url, $option): array {
        $ch = curl_init();
        if ($ch !== false) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_REFERER, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
            if (isset($option['token']) && $option['token'] !== '') {
                $headers = [
                    'Authorization: token ' . $option['token'],
                    'User-Agent: AnalyticsApp',
                    'Accept: application/vnd.github.v3+json'
                ];
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            }
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
