<?php
namespace OCA\Analytics\Tests\Datasource;

use OCA\Analytics\Datasource\GithubCommunitySla;
use OCA\Analytics\Tests\Stubs\FakeL10N;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class GithubCommunitySlaTest extends TestCase {
    public function testReadDataCalculatesSla(): void {
        $datasource = new class(new FakeL10N(), new NullLogger()) extends GithubCommunitySla {
            protected array $repositories = ['owner/repo1'];
            protected array $excludedAuthors = ['employee1'];

            protected function getCurlData($url, $option): array {
                if (str_contains($url, '/issues/1/events')) {
                    return ['data' => [
                        ['event' => 'labeled', 'label' => ['name' => '1. to develop'], 'created_at' => '2025-01-05T00:00:00Z']
                    ], 'http_code' => 200];
                }
                if (str_contains($url, '/issues?')) {
                    return ['data' => [
                        ['number' => 1, 'created_at' => '2025-01-01T00:00:00Z', 'user' => ['login' => 'communityUser']],
                        ['number' => 2, 'created_at' => '2025-01-05T00:00:00Z', 'user' => ['login' => 'employee1']]
                    ], 'http_code' => 200];
                }
                if (str_contains($url, '/pulls?')) {
                    return ['data' => [
                        ['number' => 10, 'created_at' => '2025-01-01T00:00:00Z', 'merged_at' => '2025-01-20T00:00:00Z', 'user' => ['login' => 'communityUser']],
                        ['number' => 11, 'created_at' => '2025-01-02T00:00:00Z', 'merged_at' => null, 'user' => ['login' => 'employee1']]
                    ], 'http_code' => 200];
                }
                return ['data' => [], 'http_code' => 200];
            }
        };

        $result = $datasource->readData([]);
        $this->assertSame(['owner/repo1','issue',1,'2025-01-01T00:00:00Z','2025-01-05T00:00:00Z',4,1], $result['data'][0]);
        $this->assertSame(['owner/repo1','pr',10,'2025-01-01T00:00:00Z','2025-01-20T00:00:00Z',19,0], $result['data'][1]);
    }
}
