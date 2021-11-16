<?php
/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <analytics@scherello.de>
 * @copyright 2021 Marcel Scherello
 */

namespace OCA\Analytics\Service;

use OCA\Analytics\Db\DatasetMapper;
use OCP\IDateTimeFormatter;
use Psr\Log\LoggerInterface;

class VariableService
{
    private $logger;
    private $DatasetMapper;
    private $IDateTimeFormatter;

    public function __construct(
        LoggerInterface $logger,
        DatasetMapper $DatasetMapper,
        IDateTimeFormatter $IDateTimeFormatter
    )
    {
        $this->logger = $logger;
        $this->DatasetMapper = $DatasetMapper;
        $this->IDateTimeFormatter = $IDateTimeFormatter;
    }

    /**
     * replace %*% text variables in name and subheader
     *
     * @param array $datasetMetadata
     * @return array
     */
    public function replaceTextVariables($datasetMetadata)
    {
        $fields = ['name', 'subheader'];
        foreach ($fields as $field) {
            isset($datasetMetadata[$field]) ? $name = $datasetMetadata[$field] : $name = '';

            preg_match_all("/%.*?%/", $name, $matches);
            if (count($matches[0]) > 0) {
                foreach ($matches[0] as $match) {
                    $replace = null;
                    if ($match === '%currentDate%') {
                        $replace = $this->IDateTimeFormatter->formatDate(time(), 'short');
                    } elseif ($match === '%currentTime%') {
                        $replace = $this->IDateTimeFormatter->formatTime(time(), 'short');
                    } elseif ($match === '%lastUpdateDate%') {
                        $timestamp = $this->DatasetMapper->getLastUpdate($datasetMetadata['dataset']);
                        $replace = $this->IDateTimeFormatter->formatDate($timestamp, 'short');
                    } elseif ($match === '%lastUpdateTime%') {
                        $timestamp = $this->DatasetMapper->getLastUpdate($datasetMetadata['dataset']);
                        $replace = $this->IDateTimeFormatter->formatTime($timestamp, 'short');
                    } elseif ($match === '%owner%') {
                        $owner = $this->DatasetMapper->getOwner($datasetMetadata['dataset']);
                        $replace = $owner;
                    }
                    if ($replace !== null) {
                        $datasetMetadata[$field] = preg_replace('/' . $match . '/', $replace, $datasetMetadata[$field]);
                    }
                }
            }
        }
        return $datasetMetadata;
    }
}