<?php
/**
 * Data Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2019 Marcel Scherello
 */

namespace OCA\Analytics\Controller;

use OCP\AppFramework\Controller;
use OCP\ILogger;
use OCP\IRequest;

class StorageController extends Controller
{
    private $logger;
    private $DBController;
    private $ThresholdController;

    public function __construct(
        string $AppName,
        IRequest $request,
        ILogger $logger,
        DbController $DBController,
        ThresholdController $ThresholdController
    )
    {
        parent::__construct($AppName, $request);
        $this->logger = $logger;
        $this->DBController = $DBController;
        $this->ThresholdController = $ThresholdController;
    }

    /**
     * Get the items for the selected category
     *
     * @NoAdminRequired
     * @param $datasetMetadata
     * @param $objectDrilldown
     * @param $dateDrilldown
     * @return array
     */
    public function read($datasetMetadata, $objectDrilldown, $dateDrilldown)
    {
        $header = array();
        if ($objectDrilldown === 'true') $header['dimension1'] = $datasetMetadata['dimension1'];
        if ($dateDrilldown === 'true') $header['dimension2'] = $datasetMetadata['dimension2'];
        $header['dimension3'] = $datasetMetadata['dimension3'];

        $data = $this->DBController->getData($datasetMetadata['id'], $objectDrilldown, $dateDrilldown);

        return empty($data) ? [
            'status' => 'nodata'
        ] : [
            'header' => $header,
            'data' => $data,
        ];
    }

    /**
     * Get the items for the selected category
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @param $dimension1
     * @param $dimension2
     * @param $dimension3
     * @return array
     * @throws \Exception
     */
    public function update(int $datasetId, $dimension1, $dimension2, $dimension3)
    {
        $dimension3 = str_replace(',', '.', $dimension3);

        $validate = $this->ThresholdController->validate($datasetId, $dimension1, $dimension2, $dimension3);
        $action = $this->DBController->createData($datasetId, $dimension1, $dimension2, $dimension3);

        $insert = $update = 0;
        if ($action === 'insert') $insert = 1;
        elseif ($action === 'update') $update = 1;

        return [
            'insert' => $insert,
            'update' => $update,
            'validate' => $validate
        ];
    }

    /**
     * delete data
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @param $dimension1
     * @param $dimension2
     * @return bool
     */
    public function delete(int $datasetId, $dimension1, $dimension2)
    {
        return $this->DBController->deleteData($datasetId, $dimension1, $dimension2);
    }
}