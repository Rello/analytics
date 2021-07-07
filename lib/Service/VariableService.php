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
use Psr\Log\LoggerInterface;

class VariableService
{
    private $logger;
    private $DatasetMapper;

    public function __construct(
        LoggerInterface $logger,
        DatasetMapper $DatasetMapper
    )
    {
        $this->logger = $logger;
        $this->DatasetMapper = $DatasetMapper;
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
            $name = $datasetMetadata[$field];

            preg_match_all("/%.*?%/", $name, $matches);
            if (count($matches[0]) > 0) {
                foreach ($matches[0] as $match) {
                    if ($match === '%currentDate%') {
                        $replace = date("m.d.y");
                    } elseif ($match === '%lastUpdate%') {
                        $timestamp = $this->DatasetMapper->getLastUpdate($datasetMetadata['id']);
                        $replace = date('d.m.Y', $timestamp);
                    } elseif ($match === '%owner%') {
                        $owner = $this->DatasetMapper->getOwner($datasetMetadata['id']);
                        $replace = $owner;
                    }
                    $datasetMetadata[$field] = preg_replace('/' . $match . '/', $replace, $datasetMetadata[$field]);
                }
            }
        }
        return $datasetMetadata;
    }
}