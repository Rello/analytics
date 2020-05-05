<?php
/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2020 Marcel Scherello
 */

namespace OCA\Analytics\Controller;

use OCA\Analytics\Activity\ActivityManager;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IUserSession;

class ApiDataController extends ApiController
{
    const UNKNOWN = 9001;
    const MISSING_PARAM = 9002;
    const NOT_FOUND = 9003;
    const NOT_ALLOWED = 9004;

    protected $errors = [];
    private $logger;
    private $userSession;
    private $ActivityManager;
    private $DatasetController;
    private $StorageController;

    public function __construct(
        $appName,
        IRequest $request,
        ILogger $logger,
        IUserSession $userSession,
        ActivityManager $ActivityManager,
        DatasetController $DatasetController,
        StorageController $StorageController
    )
    {
        parent::__construct(
            $appName,
            $request,
            'POST'
            );
        $this->logger = $logger;
        $this->userSession = $userSession;
        $this->ActivityManager = $ActivityManager;
        $this->DatasetController = $DatasetController;
        $this->StorageController = $StorageController;
    }

    /**
     * @CORS
     * @NoCSRFRequired
     * @NoAdminRequired
     * @param int $datasetId
     * @return DataResponse
     * @throws \Exception
     */
    public function addData(int $datasetId)
    {
        $params = $this->request->getParams();
        //$this->logger->debug($datasetId);
        $datasetMetadata = $this->DatasetController->getOwnDataset($datasetId);

        if (empty($datasetMetadata)) {
            $this->errors[] = 'Unknown report or dataset';
            return $this->requestResponse(false, self::NOT_FOUND, implode(',', $this->errors));
        } elseif ((int)$datasetMetadata['type'] !== 2) {
            $this->errors[] = 'Report does not allow data maintenance';
            return $this->requestResponse(false, self::NOT_ALLOWED, implode(',', $this->errors));
        }

        if (!isset($params['dimension1'])) {
            $this->errors[] = 'Dimension 1 required';
        } elseif (!isset($params['dimension2'])) {
            $this->errors[] = 'Dimension 2 required';
        } elseif (!isset($params['dimension3'])) {
            $this->errors[] = 'Dimension 3 required';
        }
        if (!empty($this->errors)) {
            return $this->requestResponse(false, self::MISSING_PARAM, implode(',', $this->errors));
        }

        $this->StorageController->update($datasetId, $params['dimension1'], $params['dimension2'], $params['dimension3']);
        $this->ActivityManager->triggerEvent($datasetId, ActivityManager::OBJECT_DATA, ActivityManager::SUBJECT_DATA_ADD_API);

        return $this->requestResponse(
            true,
            Http::STATUS_OK,
            'Data update successfull');
    }

    /**
     * @param bool $success
     * @param int|null $code
     * @param string|null $message
     * @return DataResponse
     */
    protected function requestResponse($success, $code = null, $message = null)
    {
        if (!$success) {
            if ($code === null) {
                $code = self::UNKNOWN;
            }
            $array = [
                'success' => false,
                'error' => ['code' => $code,
                    'message' => $message
                ]
            ];
        } else {
            $array = [
                'success' => true,
                'message' => $message
            ];
        }
        $response = new DataResponse();
        $response->setData($array)->render();
        return $response;
    }
    // curl -u admin:bwGnD-tSTPS-mJM7j-6WBFD-M9WxS -d '{"dimension1": "x", "dimension2": "x", "dimension3": "333,3"}' -X POST -H "Content-Type: application/json" http://nc16/nextcloud/apps/analytics/api/1.0/adddata/10
}