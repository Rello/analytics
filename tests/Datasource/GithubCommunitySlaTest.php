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
                $recent = gmdate(DATE_ATOM, time() - 2 * 86400);
                return ['data' => [
                    'data' => [
                        'repository' => [
                            'issues' => [
                                'nodes' => [
                                    [
                                        'number' => 1,
                                        'createdAt' => '2025-01-01T00:00:00Z',
                                        'updatedAt' => '2025-01-05T00:00:00Z',
                                        'closedAt' => null,
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
                                        'createdAt' => '2025-01-01T00:00:00Z',
                                        'updatedAt' => '2025-01-03T00:00:00Z',
                                        'closedAt' => '2025-01-03T00:00:00Z',
                                        'author' => ['login' => 'communityUser'],
                                        'timelineItems' => ['nodes' => []]
                                    ],
                                    [
                                        'number' => 3,
                                        'createdAt' => $recent,
                                        'updatedAt' => $recent,
                                        'closedAt' => null,
                                        'author' => ['login' => 'communityUser'],
                                        'timelineItems' => ['nodes' => []]
                                    ],
                                    [
                                        'number' => 4,
                                        'createdAt' => '2025-01-05T00:00:00Z',
                                        'updatedAt' => '2025-01-06T00:00:00Z',
                                        'closedAt' => null,
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
                                    ]
                                ]
                            ]
                        ]
                    ]
                ], 'http_code' => 200];
            }
        };

        $recent = gmdate(DATE_ATOM, time() - 2 * 86400);
        $expectedRecentDays = (new \DateTime($recent))->diff(new \DateTime())->days;

        $result = $datasource->readData([
            'days' => 10000,
            'repo' => 'owner/repo1',
            'exclude' => 'employee1',
            'sla' => 14,
        ]);
        $this->assertCount(4, $result['data']);
        $this->assertSame(['owner/repo1','issue',1,'2025-01-01T00:00:00Z','2025-01-05T00:00:00Z',4,1,1], $result['data'][0]);
        $this->assertSame(['owner/repo1','issue',2,'2025-01-01T00:00:00Z','2025-01-03T00:00:00Z',2,1,1], $result['data'][1]);
        $this->assertSame('owner/repo1', $result['data'][2][0]);
        $this->assertSame('issue', $result['data'][2][1]);
        $this->assertSame(3, $result['data'][2][2]);
        $this->assertSame($recent, $result['data'][2][3]);
        $this->assertSame('', $result['data'][2][4]);
        $this->assertSame($expectedRecentDays, $result['data'][2][5]);
        $this->assertSame(1, $result['data'][2][6]);
        $this->assertSame(['owner/repo1','pr',10,'2025-01-01T00:00:00Z','2025-01-20T00:00:00Z',19,0,1], $result['data'][3]);
    }
}
