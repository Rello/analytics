<?php
/**
 * Data Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2020 Marcel Scherello
 */

namespace OCA\Analytics\Controller;

use OCA\Analytics\Db\StorageMapper;
use OCP\AppFramework\Controller;
use OCP\ILogger;
use OCP\IRequest;

class StorageController extends Controller
{
    private $logger;
    private $StorageMapper;
    private $ThresholdController;

    public function __construct(
        string $AppName,
        IRequest $request,
        ILogger $logger,
        StorageMapper $StorageMapper,
        ThresholdController $ThresholdController
    )
    {
        parent::__construct($AppName, $request);
        $this->logger = $logger;
        $this->StorageMapper = $StorageMapper;
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

        $data = $this->StorageMapper->getData($datasetMetadata['id'], $objectDrilldown, $dateDrilldown);

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
     * @param string|null $user_id
     * @return array
     * @throws \Exception
     */
    public function update(int $datasetId, $dimension1, $dimension2, $dimension3, string $user_id = null)
    {
        $dimension3 = $this->floatvalue($dimension3);

        $validate = $this->ThresholdController->validate($datasetId, $dimension1, $dimension2, $dimension3);
        $action = $this->StorageMapper->createData($datasetId, $dimension1, $dimension2, $dimension3, $user_id);

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
        return $this->StorageMapper->deleteData($datasetId, $dimension1, $dimension2);
    }

    private function floatvalue($val)
    {
        $val = str_replace(",", ".", $val);
        $val = preg_replace('/\.(?=.*\.)/', '', $val);
        $val = preg_replace('/[^0-9-.]+/', '', $val);
        if (is_numeric($val)) {
            return number_format(floatval($val), 2, '.', '');
        } else {
            return false;
        }
    }

}