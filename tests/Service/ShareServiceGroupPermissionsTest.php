<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Tests\Service;

use OCA\Analytics\Db\PanoramaMapper;
use OCA\Analytics\Db\ReportMapper;
use OCA\Analytics\Db\ShareMapper;
use OCA\Analytics\Service\ShareService;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ShareServiceGroupPermissionsTest extends TestCase {
	public function testChildrenInheritPermissionsFromUserAndGroupShares(): void {
		foreach (['report', 'panorama'] as $itemType) {
			foreach ([ShareService::SHARE_TYPE_USER, ShareService::SHARE_TYPE_GROUP] as $shareType) {
				foreach (['1', '2', null] as $permissions) {
					$service = $this->buildService($itemType, $shareType, $permissions);
					$child = $itemType === 'report' ? $service->getSharedReport(11) : $service->getSharedPanorama(11);
					$this->assertSame($permissions === null ? 1 : (int)$permissions, $child['permissions']);
					$this->assertSame(ShareService::SHARE_TYPE_GROUP, $child['isShare']);
					$this->assertSame('owner', $child['user_id']);
				}
			}
		}
	}

	public function testDirectShareRetainsItsPermissions(): void {
		foreach (['report', 'panorama'] as $itemType) {
			$service = $this->buildService($itemType, ShareService::SHARE_TYPE_USER, '2', true);
			$child = $itemType === 'report' ? $service->getSharedReport(11) : $service->getSharedPanorama(11);
			$this->assertSame('1', $child['permissions']);
			$this->assertSame(ShareService::SHARE_TYPE_USER, $child['isShare']);
		}
	}

	private function buildService(string $itemType, int $shareType, ?string $permissions, bool $directShare = false): ShareService {
		$group = ['id' => 10, 'type' => 0, 'user_id' => 'owner', 'shareType' => $shareType,
			'shareUid_owner' => $shareType === ShareService::SHARE_TYPE_USER ? 'recipient' : 'team'];
		if ($permissions !== null) {
			$group['permissions'] = $permissions;
		}
		$child = ['id' => 11, 'type' => 1, 'parent' => 10, 'user_id' => 'owner'];
		$shares = [$group];
		if ($directShare) {
			$shares[] = $child + ['shareType' => ShareService::SHARE_TYPE_USER, 'shareUid_owner' => 'recipient', 'permissions' => '1'];
		}
		$shareMapper = $this->createMock(ShareMapper::class);
		$shareMapper->expects($this->once())
			->method($itemType === 'report' ? 'getAllSharedReports' : 'getAllSharedPanoramas')->willReturn($shares);
		$mapper = $this->createMock($itemType === 'report' ? ReportMapper::class : PanoramaMapper::class);
		$mapper->expects($this->once())
			->method($itemType === 'report' ? 'getReportsByGroup' : 'getPanoramasByGroup')
			->with(10, 'owner')->willReturn([$child]);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('recipient');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$groupManager = new class {
			public function getUserGroups($user): array {
				return ['team' => true];
			}
		};
		$service = (new \ReflectionClass(ShareService::class))->newInstanceWithoutConstructor();
		foreach ([
			'ShareMapper' => $shareMapper,
			$itemType === 'report' ? 'ReportMapper' : 'PanoramaMapper' => $mapper,
			'userSession' => $session,
			'groupManager' => $groupManager,
			'logger' => $this->createMock(LoggerInterface::class),
		] as $name => $value) {
			(new \ReflectionProperty(ShareService::class, $name))->setValue($service, $value);
		}
		return $service;
	}
}
