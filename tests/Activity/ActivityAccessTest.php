<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Tests\Activity;

use OCA\Analytics\Activity\ActivityManager;
use OCA\Analytics\Activity\Provider;
use OCA\Analytics\Db\DatasetMapper;
use OCA\Analytics\Db\PanoramaMapper;
use OCA\Analytics\Db\ReportMapper;
use OCA\Analytics\Db\ShareMapper;
use OCA\Analytics\Service\ShareService;
use OCA\Analytics\Tests\Stubs\FakeL10N;
use OCP\Activity\Exceptions\UnknownActivityException;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\App\IAppManager;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ActivityAccessTest extends TestCase {
	public function testActivityManagerSkipsRecipientsWithoutAnalyticsAccess(): void {
		$event = new FakeActivityEvent();
		$activityBackend = new RecordingActivityBackend($event);

		$shareMapper = $this->createMock(ShareMapper::class);
		$shareMapper->expects($this->once())
			->method('getSharedReceiver')
			->with(ShareService::SHARE_ITEM_TYPE_REPORT, 10)
			->willReturn([
				['uid_owner' => 'blocked'],
				['uid_owner' => 'allowed'],
			]);

		$reportMapper = $this->createMock(ReportMapper::class);
		$reportMapper->expects($this->once())
			->method('readOwn')
			->with(10)
			->willReturn(['name' => 'Revenue']);

		$userManager = new FakeUserManager([
			'author' => new FakeUser('author'),
			'allowed' => new FakeUser('allowed'),
			'blocked' => new FakeUser('blocked'),
		]);
		$appManager = new FakeAppManager([
			'author' => true,
			'allowed' => true,
			'blocked' => false,
		]);

		$manager = new ActivityManager(
			$activityBackend,
			$shareMapper,
			'author',
			$reportMapper,
			$this->createMock(DatasetMapper::class),
			$this->createMock(LoggerInterface::class),
			$this->createMock(PanoramaMapper::class),
			$appManager,
			$userManager
		);

		$manager->triggerEvent(10, ActivityManager::OBJECT_REPORT, ActivityManager::SUBJECT_REPORT_SHARE);

		$this->assertSame(['allowed', 'author'], $activityBackend->publishedUsers);
	}

	public function testProviderHidesAnalyticsActivityForRestrictedUsers(): void {
		$provider = $this->createProvider('blocked', ['blocked' => false]);

		$this->expectException(UnknownActivityException::class);

		$provider->parse('en', FakeActivityEvent::analyticsReportShare());
	}

	public function testProviderRendersAnalyticsActivityForAllowedUsers(): void {
		$event = FakeActivityEvent::analyticsReportShare();
		$provider = $this->createProvider('allowed', ['allowed' => true]);

		$provider->parse('en', $event);

		$this->assertSame('"Revenue"', $event->richSubjectParameters['report']['name']);
		$this->assertSame('https://cloud.example/index.php/apps/analytics/r/10', $event->richSubjectParameters['report']['link']);
	}

	private function createProvider(string $userId, array $enabledUsers): Provider {
		return new Provider(
			new FakeUrlGenerator(),
			new FakeL10N(),
			new FakeAppManager($enabledUsers),
			new FakeUserManager([
				$userId => new FakeUser($userId),
			]),
			$userId
		);
	}
}

class FakeActivityEvent implements IEvent {
	private $app;
	private $type;
	private $author;
	private $objectId;
	private $objectName;
	private $subject;
	private $subjectParameters = [];
	private $affectedUser;
	public $richSubjectParameters = [];

	public static function analyticsReportShare(): self {
		$event = new self();
		$event->setApp('analytics')
			->setType(ActivityManager::OBJECT_REPORT)
			->setAuthor('author')
			->setObject('report', 10, 'Revenue')
			->setSubject(ActivityManager::SUBJECT_REPORT_SHARE, ['author' => 'author']);

		return $event;
	}

	public function getApp() {
		return $this->app;
	}

	public function setApp($app) {
		$this->app = $app;
		return $this;
	}

	public function setType($type) {
		$this->type = $type;
		return $this;
	}

	public function getType() {
		return $this->type;
	}

	public function setAuthor($author) {
		$this->author = $author;
		return $this;
	}

	public function getAuthor() {
		return $this->author;
	}

	public function setObject($objectType, $objectId, $objectName = '') {
		$this->objectId = $objectId;
		$this->objectName = $objectName;
		return $this;
	}

	public function getObjectId() {
		return $this->objectId;
	}

	public function getObjectName() {
		return $this->objectName;
	}

	public function setSubject($subject, array $parameters = []) {
		$this->subject = $subject;
		$this->subjectParameters = $parameters;
		return $this;
	}

	public function getSubject() {
		return $this->subject;
	}

	public function getSubjectParameters() {
		return $this->subjectParameters;
	}

	public function setTimestamp($timestamp) {
		return $this;
	}

	public function setAffectedUser($user) {
		$this->affectedUser = $user;
		return $this;
	}

	public function getAffectedUser() {
		return $this->affectedUser;
	}

	public function setRichSubject($subject, array $parameters = []) {
		$this->richSubjectParameters = $parameters;
		return $this;
	}

	public function setIcon($icon) {
		return $this;
	}
}

class RecordingActivityBackend implements IManager {
	/** @var FakeActivityEvent */
	private $event;
	public $publishedUsers = [];

	public function __construct(FakeActivityEvent $event) {
		$this->event = $event;
	}

	public function generateEvent() {
		return $this->event;
	}

	public function publish(IEvent $event) {
		$this->publishedUsers[] = $event->getAffectedUser();
	}
}

class FakeAppManager implements IAppManager {
	private $enabledUsers;

	public function __construct(array $enabledUsers) {
		$this->enabledUsers = $enabledUsers;
	}

	public function isEnabledForUser($appId, $user = null) {
		if ($user === null) {
			return false;
		}

		return $appId === 'analytics' && ($this->enabledUsers[$user->getUID()] ?? false);
	}
}

class FakeUserManager implements IUserManager {
	private $users;

	public function __construct(array $users) {
		$this->users = $users;
	}

	public function get($uid) {
		return $this->users[$uid] ?? null;
	}

	public function userExists($uid) {
		return isset($this->users[$uid]);
	}
}

class FakeUser implements IUser {
	private $uid;

	public function __construct(string $uid) {
		$this->uid = $uid;
	}

	public function getUID() {
		return $this->uid;
	}
}

class FakeUrlGenerator implements IURLGenerator {
	public function getAbsoluteURL($url) {
		return 'https://cloud.example' . $url;
	}

	public function imagePath($appName, $file) {
		return '/apps/' . $appName . '/img/' . $file;
	}

	public function linkToRouteAbsolute($routeName, $arguments = []) {
		return 'https://cloud.example/index.php/apps/analytics/';
	}
}
