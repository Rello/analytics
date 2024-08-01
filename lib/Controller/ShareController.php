<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Controller;

use OCA\Analytics\Service\PanoramaService;
use OCA\Analytics\Service\ShareService;
use OCA\Analytics\Service\ReportService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\DB\Exception;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class ShareController extends Controller {
	const SHARE_TYPE_USER = 0;
	const SHARE_TYPE_GROUP = 1;
	const SHARE_TYPE_USERGROUP = 2;
	const SHARE_TYPE_LINK = 3;
	const SHARE_TYPE_ROOM = 10;

	/** @var LoggerInterface */
	private $logger;
	private $ShareService;
	private $ReportService;
	private $PanoramaService;

	public function __construct(
		$appName,
		IRequest $request,
		LoggerInterface $logger,
		ShareService $ShareService,
		ReportService $ReportService,
		PanoramaService $PanoramaService
	) {
		parent::__construct($appName, $request);
		$this->logger = $logger;
		$this->ShareService = $ShareService;
		$this->ReportService = $ReportService;
		$this->PanoramaService = $PanoramaService;
	}

	/**
	 * create a new share
	 *
	 * @NoAdminRequired
	 * @param $item_type
	 * @param $item_source
	 * @param $type
	 * @param $user
	 * @return DataResponse
	 * @throws Exception
	 */
	public function create($item_type, $item_source, $type, $user) {
		if (($item_type === ShareService::SHARE_ITEM_TYPE_REPORT && $this->ReportService->isOwn($item_source))
		|| ($item_type === ShareService::SHARE_ITEM_TYPE_PANORAMA && $this->PanoramaService->isOwn($item_source))) {
			return new DataResponse($this->ShareService->create($item_type, $item_source, $type, $user));
		} else {
			return new DataResponse(false);
		}
	}

	/**
	 * get all shares for a report
	 *
	 * @NoAdminRequired
	 * @param $item_source
	 * @return DataResponse
	 * @throws Exception
	 */
	public function readReport($item_source) {
		if ($this->ReportService->isOwn($item_source)) {
			return new DataResponse($this->ShareService->read(ShareService::SHARE_ITEM_TYPE_REPORT, $item_source));
		} else {
			return new DataResponse(false);
		}
	}

	/**
	 * get all shares for a panorama
	 *
	 * @NoAdminRequired
	 * @param $item_source
	 * @return DataResponse
	 * @throws Exception
	 */
	public function readPanorama($item_source) {
		if ($this->PanoramaService->isOwn($item_source)) {
			return new DataResponse($this->ShareService->read(ShareService::SHARE_ITEM_TYPE_PANORAMA, $item_source));
		} else {
			return new DataResponse(false);
		}
	}

	/**
	 * update/set share password
	 *
	 * @NoAdminRequired
	 * @param $shareId
	 * @param null $password
	 * @param null $canEdit
	 * @param null $domain
	 * @return DataResponse
	 */
	public function update($shareId, $password = null, $canEdit = null, $domain = null) {
		return new DataResponse($this->ShareService->update($shareId, $password, $canEdit, $domain));
	}

	/**
	 * delete a share
	 *
	 * @NoAdminRequired
	 * @param $shareId
	 * @return DataResponse
	 */
	public function delete($shareId) {
		return new DataResponse($this->ShareService->delete($shareId));
	}
}