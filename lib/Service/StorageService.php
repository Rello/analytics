<?php
/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <analytics@scherello.de>
 * @copyright 2019-2022 Marcel Scherello
 */

namespace OCA\Analytics\Service;

use OCA\Analytics\Db\StorageMapper;
use OCP\DB\Exception;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class StorageService
{
    private $logger;
    private $StorageMapper;
    private $ThresholdService;
    private $DatasetService;
    private $ReportService;
    private $VariableService;

    public function __construct(
        LoggerInterface  $logger,
        StorageMapper    $StorageMapper,
        DatasetService   $DatasetService,
        ThresholdService $ThresholdService,
        VariableService  $VariableService,
        ReportService    $ReportService
    )
    {
        $this->logger = $logger;
        $this->StorageMapper = $StorageMapper;
        $this->DatasetService = $DatasetService;
        $this->ThresholdService = $ThresholdService;
        $this->VariableService = $VariableService;
        $this->ReportService = $ReportService;
    }

    /**
     * Get the items for the selected category
     *
     * @NoAdminRequired
     * @param $datasetId
     * @param $reportMetadata
     * @return array
     * @throws Exceptionx
     */
    public function read($datasetId, $reportMetadata)
    {
        $availableDimensions = array();
        $header = array();
        $datasetMetadata = $this->DatasetService->read($datasetId);
        if ($reportMetadata['filteroptions'] !== null) {
            $options = json_decode($reportMetadata['filteroptions'], true);
        } else {
            $options = null;
        }

        if (!empty($datasetMetadata)) {
            // output the dimensions available for filtering of this dataset
            // this needs to map the technical name to its display name in the report
            $availableDimensions['dimension1'] = $datasetMetadata['dimension1'];
            $availableDimensions['dimension2'] = $datasetMetadata['dimension2'];

            // return the header texts of the data being transferred according to the current drill down state selected by user
            // if the dimension is not part of the drill down filter, it is not hidden => to be displayed
            if (!isset($options['drilldown']['dimension1'])) $header[0] = $datasetMetadata['dimension1'];
            if (!isset($options['drilldown']['dimension2'])) $header[1] = $datasetMetadata['dimension2'];
            $header[6] = $datasetMetadata['value'];
            $header = array_values($header);

            $data = $this->StorageMapper->read($datasetMetadata['id'], $options);
            $data = array_values($data);
            foreach ($data as $key => $value) {
                $data[$key] = array_values($value);
            }
        }

        return empty($data) ? [
            'dimensions' => $availableDimensions,
            'status' => 'nodata',
            'error' => 0
        ] : [
            'header' => $header,
            'dimensions' => $availableDimensions,
            'data' => $data,
            'error' => 0
        ];
    }

    /**
     * Get the items for the selected category
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @param $dimension1
     * @param $dimension2
     * @param $value
     * @param string|null $user_id
     * @param $bulkInsert
     * @param $aggregation
     * @return array
     * @throws Exception
     */
    public function update(int $datasetId, $dimension1, $dimension2, $value, string $user_id = null, $bulkInsert = null, $aggregation = null)
    {
        TODO:
        //dates in both columns
        $dimension2 = $this->convertGermanDateFormat($dimension2);
        //convert date into timestamp
        //$timestamp = $this->convertGermanDateFormat($timestamp);
        $value = $this->floatvalue($value);
        $validate = '';
        $insert = $update = $error = $action = 0;

        // replace text variables like %now%
        $dimension1 = $this->VariableService->replaceTextVariablesSingle($dimension1);
        $dimension2 = $this->VariableService->replaceTextVariablesSingle($dimension2);

        if ($value !== false) {
            try {
                $action = $this->StorageMapper->create($datasetId, $dimension1, $dimension2, $value, $user_id, null, $bulkInsert, $aggregation);
            } catch (\Exception $e) {
                $error = 1;
            }
            if ($action === 'insert') $insert = 1;
            elseif ($action === 'update') $update = 1;
        } else {
            $error = 1;
        }

        // get all reports for the dataset and evaluate their thresholds for push notifications
        if ($error === 0) {
            foreach ($this->ReportService->reportsForDataset($datasetId) as $report) {
                $validateResult = $this->ThresholdService->validate($report['id'], $dimension1, $dimension2, $value, $insert);
                if ($validateResult !== '') $validate = $validateResult;
            }
        }

        return [
            'insert' => $insert,
            'update' => $update,
            'error' => $error,
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
     * @param string|null $user_id
     * @return bool
     */
    public function delete(int $datasetId, $dimension1, $dimension2, string $user_id = null)
    {
        return $this->StorageMapper->delete($datasetId, $dimension1, $dimension2, $user_id);
    }

    /**
     * Simulate delete data
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @param $dimension1
     * @param $dimension2
     * @return array
     * @throws Exception
     */
    public function deleteSimulate(int $datasetId, $dimension1, $dimension2)
    {
        return $this->StorageMapper->deleteSimulate($datasetId, $dimension1, $dimension2);
    }

    /**
     * Delete data with variables; used for data deletion jobs
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @param $filter
     * @return bool
     * @throws Exception
     */
    public function deleteWithFilter(int $datasetId, $filter)
    {
        return $this->StorageMapper->deleteWithFilter($datasetId, $filter);
    }

    /**
     * Simulate delete data with variables; used for data deletion jobs
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @param $filter
     * @return bool
     * @throws Exception
     */
    public function deleteWithFilterSimulate(int $datasetId, $filter)
    {
        return $this->StorageMapper->deleteWithFilterSimulate($datasetId, $filter);
    }

    /**
     * Get the number of records for a dataset
     * @param int $datasetId
     * @param string|null $user_id
     * @return array
     */
    public function getRecordCount(int $datasetId, string $user_id = null)
    {
        return $this->StorageMapper->getRecordCount($datasetId, $user_id);
    }

    private function floatvalue($val)
    {
        // if value is a 3 digit comma number with one leading zero like 0,111, it should not go through the 1000 separator removal
        if (preg_match('/(?<=\b0)\,(?=\d{3}\b)/', $val) === 0 && preg_match('/(?<=\b0)\.(?=\d{3}\b)/', $val) === 0) {
            // remove , as 1000 separator
            $val = preg_replace('/(?<=\d)\,(?=\d{3}\b)/', '', $val);
            // remove . as 1000 separator
            $val = preg_replace('/(?<=\d)\.(?=\d{3}\b)/', '', $val);
        }
        // convert remaining comma to decimal point
        $val = str_replace(",", ".", $val);
        if (is_numeric($val)) {
            return number_format(floatval($val), 2, '.', '');
        } else {
            return false;
        }
    }

    private function convertGermanDateFormat($val)
    {
        if ($val !== null) {
            $fullString = explode(' ', $val);
            $dateLength = strlen($fullString[0]);
            $dateParts = explode('.', $fullString[0]);

            if ($dateLength >= 6 && $dateLength <= 10 && count($dateParts) === 3) {
                // is most likely a german date format 20.02.2020
                $fullString[0] = $dateParts[2] . '-' . sprintf('%02d', $dateParts[1]) . '-' . sprintf('%02d', $dateParts[0]);
                $val = implode(' ', $fullString);
            }
        }
        return $val;
    }
}