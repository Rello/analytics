<?php
namespace OCA\Analytics\Tests\Datasource;

use OCA\Analytics\Datasource\GithubCommunitySla;
use OCA\Analytics\Tests\Stubs\FakeL10N;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class GithubCommunitySlaTest extends TestCase {
    public function testReadDataCalculatesSla(): void {
        $datasource = new class(new FakeL10N(), new NullLogger()) extends GithubCommunitySla {
            protected function getGraphqlData(string $query, array $variables, array $option): array {
                return ['data' => [
                    'data' => [
                        'repository' => [
                            'issues' => [
                                'nodes' => [
                                    [
                                        'number' => 1,
                                        'createdAt' => '2025-01-01T00:00:00Z',
                                        'updatedAt' => '2025-01-05T00:00:00Z',
                                        'author' => ['login' => 'communityUser'],
                                        'timelineItems' => [
                                            'nodes' => [
                                                [
                                                    '__typename' => 'UnlabeledEvent',
                                                    'label' => ['name' => '0. Needs triage'],
                                                    'createdAt' => '2025-01-05T00:00:00Z'
                                                ]
                                            ]
                                        ]
                                    ],
                                    [
                                        'number' => 2,
                                        'createdAt' => '2025-01-05T00:00:00Z',
                                        'updatedAt' => '2025-01-06T00:00:00Z',
                                        'author' => ['login' => 'employee1'],
                                        'timelineItems' => ['nodes' => []]
                                    ]
                                ]
                            ],
                            'pullRequests' => [
                                'nodes' => [
                                    [
                                        'number' => 10,
                                        'createdAt' => '2025-01-01T00:00:00Z',
                                        'mergedAt' => null,
                                        'closedAt' => '2025-01-20T00:00:00Z',
                                        'updatedAt' => '2025-01-20T00:00:00Z',
                                        'author' => ['login' => 'communityUser']
                                    ],
                                    [
                                        'number' => 11,
                                        'createdAt' => '2025-01-02T00:00:00Z',
                                        'mergedAt' => null,
                                        'closedAt' => null,
                                        'updatedAt' => '2025-01-02T00:00:00Z',
                                        'author' => ['login' => 'employee1']
                                    ],
                                    [
                                        'number' => 12,
                                        'createdAt' => '2000-01-01T00:00:00Z',
                                        'mergedAt' => '2000-01-02T00:00:00Z',
                                        'closedAt' => '2000-01-02T00:00:00Z',
                                        'updatedAt' => '2000-01-02T00:00:00Z',
                                        'author' => ['login' => 'communityUser']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ], 'http_code' => 200];
            }
        };

        $result = $datasource->readData([
            'days' => 30,
            'repo' => 'owner/repo1',
            'exclude' => 'employee1',
            'sla' => 14,
        ]);
        $this->assertCount(2, $result['data']);
        $this->assertSame(['owner/repo1','issue',1,'2025-01-01T00:00:00Z','2025-01-05T00:00:00Z',4,1,1], $result['data'][0]);
        $this->assertSame(['owner/repo1','pr',10,'2025-01-01T00:00:00Z','2025-01-20T00:00:00Z',19,0,1], $result['data'][1]);
    }
}
