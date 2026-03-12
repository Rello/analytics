<?php
namespace OCA\Analytics\Tests\Datasource;

use OCA\Analytics\Datasource\Github;
use OCA\Analytics\Tests\Stubs\FakeL10N;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class GithubTest extends TestCase
{
	private Github $github;

	protected function setUp(): void
	{
		$this->github = new Github(new FakeL10N(), new NullLogger());
	}

	public function testBuildHttpErrorResultForUnauthorizedResponse(): void
	{
		$method = new \ReflectionMethod(Github::class, 'buildHttpErrorResult');
		$method->setAccessible(true);

		$result = $method->invoke(
			$this->github,
			401,
			['data' => ['message' => 'Bad credentials'], 'http_code' => 401],
			['cacheable' => true, 'key' => 'k1', 'notModified' => false]
		);

		$this->assertSame('Missing or invalid GitHub access token', $result['error']);
		$this->assertSame([], $result['data']);
	}

	public function testBuildHttpErrorResultForRateLimitResponse(): void
	{
		$method = new \ReflectionMethod(Github::class, 'buildHttpErrorResult');
		$method->setAccessible(true);

		$result = $method->invoke(
			$this->github,
			403,
			['data' => ['message' => 'API rate limit exceeded'], 'http_code' => 403],
			['cacheable' => true, 'key' => 'k1', 'notModified' => false]
		);

		$this->assertSame('Rate limit exceeded', $result['error']);
		$this->assertSame([], $result['data']);
	}
}
