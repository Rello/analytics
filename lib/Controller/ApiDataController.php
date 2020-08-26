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
     * add data via there database names
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
     * add data via there real field names
     * @CORS
     * @NoCSRFRequired
     * @NoAdminRequired
     * @param int $datasetId
     * @return DataResponse
     * @throws \Exception
     */
    public function addDataV2(int $datasetId)
    {
        $params = $this->request->getParams();
        $this->logger->debug('array: ' . json_encode($params));
        $datasetMetadata = $this->DatasetController->getOwnDataset($datasetId);

        if (empty($datasetMetadata)) {
            $this->errors[] = 'Unknown report or dataset';
            return $this->requestResponse(false, self::NOT_FOUND, implode(',', $this->errors));
        } elseif ((int)$datasetMetadata['type'] !== 2) {
            $this->errors[] = 'Report does not allow data maintenance';
            return $this->requestResponse(false, self::NOT_ALLOWED, implode(',', $this->errors));
        }

        foreach ($params['data'] as $dataArray) {
            $this->logger->debug('array: ' . json_encode($dataArray));

            if (isset($dataArray['dimension1'])) {
                $dimension1 = $dataArray['dimension1'];
            } elseif (isset($dataArray[$datasetMetadata['dimension1']])) {
                $dimension1 = $dataArray[$datasetMetadata['dimension1']];
            } else {
                $this->errors[] = 'Dimension 1 required';
            }

            if (isset($dataArray['dimension2'])) {
                $dimension2 = $dataArray['dimension2'];
            } elseif (isset($dataArray[$datasetMetadata['dimension2']])) {
                $dimension2 = $dataArray[$datasetMetadata['dimension2']];
            } else {
                $this->errors[] = 'Dimension 2 required';
            }

            if (isset($dataArray['value'])) {
                $value = $dataArray['value'];
            } elseif (isset($dataArray[$datasetMetadata['value']])) {
                $value = $dataArray[$datasetMetadata['value']];
            } else {
                $this->errors[] = 'Value required';
            }

            if (!empty($this->errors)) {
                return $this->requestResponse(false, self::MISSING_PARAM, implode(',', $this->errors));
            }

            $this->StorageController->update($datasetId, $dimension1, $dimension2, $value);
            $this->ActivityManager->triggerEvent($datasetId, ActivityManager::OBJECT_DATA, ActivityManager::SUBJECT_DATA_ADD_API);
        }

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
    // curl -u Admin:2sroW-SxRcK-AmdsF-RYMJ5-CKSyf -d '{"dimension1": "x", "simension2": "x", "dimension3": "333,3"}' -X POST -H "Content-Type: application/json" http://nc18/nextcloud/apps/analytics/api/1.0/adddata/158
    // curl -u Admin:2sroW-SxRcK-AmdsF-RYMJ5-CKSyf -d '[{"Spalte 1": "x", "Spalte 2": "x", "toller wert": "333,3"}]' -X POST -H "Content-Type: application/json" http://nc18/nextcloud/apps/analytics/api/2.0/adddata/158
    // curl -u Admin:2sroW-SxRcK-AmdsF-RYMJ5-CKSyf -d '{"data":[{"Spalte 1": "a", "Spalte 2": "a", "toller wert": "1"}, {"dimension1": "b", "dimension2": "b", "value": "2"}]}' -X POST -H "Content-Type: application/json" http://nc18/nextcloud/apps/analytics/api/2.0/adddata/158

}