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

	public function testBuildHttpErrorResultForForbiddenPackageAccessResponse(): void
	{
		$method = new \ReflectionMethod(Github::class, 'buildHttpErrorResult');
		$method->setAccessible(true);

		$result = $method->invoke(
			$this->github,
			403,
			['data' => ['message' => 'Resource not accessible by personal access token'], 'http_code' => 403],
			['cacheable' => true, 'key' => 'k1', 'notModified' => false]
		);

		$this->assertSame('GitHub API error: Resource not accessible by personal access token', $result['error']);
		$this->assertSame([], $result['data']);
	}

	public function testBuildCurlOptionsVerifiesTlsWhenTokenIsConfigured(): void
	{
		$method = new \ReflectionMethod(Github::class, 'buildCurlOptions');
		$method->setAccessible(true);

		$options = $method->invoke(
			$this->github,
			'https://api.github.com/repos/nextcloud/server',
			['token' => 'secret-token']
		);

		$this->assertTrue($options[CURLOPT_SSL_VERIFYPEER]);
		$this->assertSame(2, $options[CURLOPT_SSL_VERIFYHOST]);
		$this->assertContains('Authorization: token secret-token', $options[CURLOPT_HTTPHEADER]);
	}

	public function testBuildPackageUrlDefaultsToOrganizationPackages(): void
	{
		$method = new \ReflectionMethod(Github::class, 'buildPackageUrl');
		$method->setAccessible(true);

		$url = $method->invoke(
			$this->github,
			['user' => 'euro-office', 'repository' => 'documentserver']
		);

		$this->assertSame('https://api.github.com/orgs/euro-office/packages/container/documentserver', $url);
	}

	public function testBuildPackageVersionsUrlUsesTaggedVersionsPage(): void
	{
		$method = new \ReflectionMethod(Github::class, 'buildPackageVersionsUrl');
		$method->setAccessible(true);

		$url = $method->invoke(
			$this->github,
			['user' => 'euro-office', 'repository' => 'documentserver']
		);

		$this->assertSame('https://github.com/orgs/euro-office/packages/container/documentserver/versions?filters%5Bversion_type%5D=tagged', $url);
	}

	public function testParsePackageDownloadRowsReturnsTagsAndDownloadCounts(): void
	{
		$method = new \ReflectionMethod(Github::class, 'parsePackageDownloadRows');
		$method->setAccessible(true);

		$rows = $method->invoke(
			$this->github,
			'<li class="Box-row">
				<a class="Label" href="/orgs/euro-office/packages/container/documentserver/1?tag=latest">latest</a>
				<a class="Label" href="/orgs/euro-office/packages/container/documentserver/1?tag=v9.3.2-rc.1">v9.3.2-rc.1</a>
				<svg class="octicon octicon-download"></svg>
				17,118
				<span class="sr-only">Version downloads</span>
			</li>
			<li class="Box-row">
				<a class="Label" href="/orgs/euro-office/packages/container/documentserver/2?tag=latest-amd64">latest-amd64</a>
				<svg class="octicon octicon-download"></svg>
				7
				<span class="sr-only">Version downloads</span>
			</li>',
			['limit' => '']
		);

		$this->assertSame(
			[
				['tag' => 'latest', 'downloads' => 17118],
				['tag' => 'v9.3.2-rc.1', 'downloads' => 17118],
				['tag' => 'latest-amd64', 'downloads' => 7],
			],
			$rows
		);
	}

	public function testParsePackageDownloadRowsHonorsLimit(): void
	{
		$method = new \ReflectionMethod(Github::class, 'parsePackageDownloadRows');
		$method->setAccessible(true);

		$rows = $method->invoke(
			$this->github,
			'<li class="Box-row">
				<a class="Label" href="/orgs/euro-office/packages/container/documentserver/1?tag=latest">latest</a>
				<svg class="octicon octicon-download"></svg>
				17,118
				<span class="sr-only">Version downloads</span>
			</li>
			<li class="Box-row">
				<a class="Label" href="/orgs/euro-office/packages/container/documentserver/2?tag=latest-amd64">latest-amd64</a>
				<svg class="octicon octicon-download"></svg>
				7
				<span class="sr-only">Version downloads</span>
			</li>',
			['limit' => '1']
		);

		$this->assertSame(
			[
				['tag' => 'latest', 'downloads' => 17118],
			],
			$rows
		);
	}
}
