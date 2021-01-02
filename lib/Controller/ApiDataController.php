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

        $this->deriveMaintenancePossible($datasetMetadata);

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
        $message = 'No -data- parameter';
        $params = $this->request->getParams();
        //$this->logger->debug('array: ' . json_encode($params));
        $datasetMetadata = $this->DatasetController->getOwnDataset($datasetId);

        $this->deriveMaintenancePossible($datasetMetadata);

        foreach ($params['data'] as $dataArray) {
            //$this->logger->debug('array: ' . json_encode($dataArray));

            $dimension1 = $this->deriveParameterNames($dataArray, $datasetMetadata, 'dimension1');
            $dimension2 = $this->deriveParameterNames($dataArray, $datasetMetadata, 'dimension2');
            $value = $this->deriveParameterNames($dataArray, $datasetMetadata, 'value');

            if (!empty($this->errors)) {
                return $this->requestResponse(false, self::MISSING_PARAM, implode(',', $this->errors));
            }

            $this->StorageController->update($datasetId, $dimension1, $dimension2, $value);
            $this->ActivityManager->triggerEvent($datasetId, ActivityManager::OBJECT_DATA, ActivityManager::SUBJECT_DATA_ADD_API);
            $message = 'Data update successfull';
        }

        return $this->requestResponse(
            true,
            Http::STATUS_OK,
            $message);
    }

    /**
     * delete data
     * @CORS
     * @NoCSRFRequired
     * @NoAdminRequired
     * @param int $datasetId
     * @return DataResponse
     * @throws \Exception
     */
    public function deleteDataV2(int $datasetId)
    {
        $message = 'No -delete- parameter';
        $params = $this->request->getParams();
        //$this->logger->debug('array: ' . json_encode($params));
        $datasetMetadata = $this->DatasetController->getOwnDataset($datasetId);

        $this->deriveMaintenancePossible($datasetMetadata);

        foreach ($params['delete'] as $dataArray) {
            $dimension1 = $this->deriveParameterNames($dataArray, $datasetMetadata, 'dimension1');
            $dimension2 = $this->deriveParameterNames($dataArray, $datasetMetadata, 'dimension2');

            if (!empty($this->errors)) {
                return $this->requestResponse(false, self::MISSING_PARAM, implode(',', $this->errors));
            }

            $this->StorageController->delete($datasetId, $dimension1, $dimension2);
            $message = 'Data deleted';
        }

        return $this->requestResponse(
            true,
            Http::STATUS_OK,
            $message);
    }

    /**
     * derive if the parameter is technical or the free text description from the report
     * @param $data
     * @param $datasetMetadata
     * @param $dimension
     * @return array | bool
     */
    protected function deriveParameterNames($data, $datasetMetadata, $dimension)
    {
        if (isset($data[$dimension])) {
            return $data[$dimension];
        } elseif (isset($data[$datasetMetadata[$dimension]])) {
            return $data[$datasetMetadata[$dimension]];
        } else {
            $this->errors[] = $dimension . ' required';
            return false;
        }
    }

    /**
     * derive if maintenance is possible
     * @param $datasetMetadata
     * @return DataResponse | bool
     */
    protected function deriveMaintenancePossible($datasetMetadata)
    {
        if (empty($datasetMetadata)) {
            $this->errors[] = 'Unknown report or dataset';
            return $this->requestResponse(false, self::NOT_FOUND, implode(',', $this->errors));
        } elseif ((int)$datasetMetadata['type'] !== 2) {
            $this->errors[] = 'Report does not allow data maintenance';
            return $this->requestResponse(false, self::NOT_ALLOWED, implode(',', $this->errors));
        }
        return true;
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
    // curl -u Admin:2sroW-SxRcK-AmdsF-RYMJ5-CKSyf -d '{"dimension1": "x", "dimension2": "x", "dimension3": "333,3"}' -X POST -H "Content-Type: application/json" http://nc18/nextcloud/apps/analytics/api/1.0/adddata/158
    // curl -u Admin:2sroW-SxRcK-AmdsF-RYMJ5-CKSyf -d '[{"Spalte 1": "x", "Spalte 2": "x", "toller wert": "333,3"}]' -X POST -H "Content-Type: application/json" http://nc18/nextcloud/apps/analytics/api/2.0/adddata/158
    // curl -u Admin:2sroW-SxRcK-AmdsF-RYMJ5-CKSyf -d '{"data":[{"Spalte 1": "a", "Spalte 2": "a", "toller wert": "1"}, {"dimension1": "b", "dimension2": "b", "value": "2"}]}' -X POST -H "Content-Type: application/json" http://nc18/nextcloud/apps/analytics/api/2.0/adddata/158

    // curl -u Admin:2sroW-SxRcK-AmdsF-RYMJ5-CKSyf -d '{"delete":[{"dimension1": "a", "dimension2": "a"}]}' -X POST -H "Content-Type: application/json" http://nc18/nextcloud/apps/analytics/api/2.0/deletedata/158
    // curl -u Admin:2sroW-SxRcK-AmdsF-RYMJ5-CKSyf -d '{"del":[{"dimension1": "a", "dimension2": "a"}]}' -X POST -H "Content-Type: application/json" http://nc18/nextcloud/apps/analytics/api/2.0/deletedata/158

}