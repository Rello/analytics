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
     * replace %*% text variables in thresholds
     *
     * @param array $thresholds
     * @return array
     */
    public function replaceThresholdsVariables($thresholds)
    {
        foreach ($thresholds as &$threshold) {
            $fields = ['dimension1', 'dimension2'];
            foreach ($fields as $field) {
                isset($threshold[$field]) ? $name = $threshold[$field] : $name = '';
                $parsed = $this->parseFilter($name);
                if (!$parsed) break;
                $threshold[$field] = $parsed['6$startDate'];
            }
        }
        return $thresholds;
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
                    } elseif ($match === '%now%') {
                        $replace = time();
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

    /**
     * replace variables in single field
     * used in: API
     *
     * @param $field
     * @return array
     */
    public function replaceTextVariablesSingle($field)
    {
        if ($field !== null) {
            preg_match_all("/%.*?%/", $field, $matches);
            if (count($matches[0]) > 0) {
                foreach ($matches[0] as $match) {
                    $replace = null;
                    if ($match === '%currentDate%') {
                        $replace = $this->IDateTimeFormatter->formatDate(time(), 'short');
                    } elseif ($match === '%currentTime%') {
                        $replace = $this->IDateTimeFormatter->formatTime(time(), 'short');
                    } elseif ($match === '%now%') {
                        $replace = time();
                    }
                    if ($replace !== null) {
                        $field = preg_replace('/' . $match . '/', $replace, $field);
                    }
                }
            }
        }
        return $field;
    }

    /**
     * replace variables in single field
     * used in: API
     *
     * @param $columns
     * @return string
     */
    public function replaceDatasourceColumns($columns)
    {
        $parsed = $this->parseFilter($columns);
        $format = $this->parseFormat($columns);
        if (!$parsed) return $columns;
        return date($format, $parsed['value']);
    }

        /**
     * replace variables in filters and apply format
     *
     * @param $reportMetadata
     * @return array
     */
    public function replaceFilterVariables($reportMetadata)
    {
        if ($reportMetadata['filteroptions'] !== null) {
            $filteroptions = json_decode($reportMetadata['filteroptions'], true);
            if (isset($filteroptions['filter'])) {
                foreach ($filteroptions['filter'] as $key => $value) {
                    $parsed = $this->parseFilter($value['value']);

                    if (!$parsed) continue;

                    // if a parser is selected in the chart options, it should also be valid here automatically
                    if (isset($reportMetadata['chartoptions'])) {
                        $chartOptions = json_decode($reportMetadata['chartoptions'], true);
                        if(isset($chartOptions['scales']['xAxes']['time']['parser'])) {
                            $format = $chartOptions['scales']['xAxes']['time']['parser'];
                        }
                    }
                    $format = $this->parseFormat($value['value']);

                    // translate commonly known X timestamp format to U for php
                    if ($format === 'X') $format = 'U';

                    $filteroptions['filter'][$key]['value'] = date($format, $parsed['value']);
                    //$filteroptions['filter'][$key]['option'] = $parsed['option'];
                }
            }
            $reportMetadata['filteroptions'] = json_encode($filteroptions);
        }
        return $reportMetadata;
    }

    /**
     * parsing of %*% variables
     *
     * @param $filter
     * @return array|bool
     */
    private function parseFilter($filter) {
        preg_match_all("/(?<=%).*(?=%)/", $filter, $matches);
        if (count($matches[0]) > 0) {
            $filter = $matches[0][0];
            preg_match('/(last|next|current|to|yester)?/', $filter, $directionMatch); // direction
            preg_match('/[0-9]+/', $filter, $offsetMatch); // how much
            preg_match('/(day|days|week|weeks|month|months|year|years)$/', $filter, $unitMatch); // unit

            if (!$directionMatch[0] || !$unitMatch[0]) {
                // no known text variables found
                return false;
            }

            // if no offset is specified, apply 1 as default
            !isset($offsetMatch[0]) ? $offset = 1: $offset = $offsetMatch[0];

            // remove "s" to unify e.g. weeks => week
            $unit = rtrim($unitMatch[0], 's');

            if ($directionMatch[0] === "last" || $directionMatch[0] === "yester") {
                // go back
                $direction = '-';
            } elseif ($directionMatch[0] === "next") {
                // go forward
                $direction = '+';
            } else {
                // current
                $direction = '+';
                $offset = 0;
            }

            // create a usable string for php like "+3 days"
            $timeString = $direction . $offset . ' ' . $unit;
            // get a timestamp of the target date
            $baseDate = strtotime($timeString);

            // get the correct format depending of the unit. e.g. first day of the month in case unit is "month"
            if ($unit === 'day') {
                $startString = 'today';
            } else {
                $startString = 'first day of this ' . $unit;
            }
            $startTS = strtotime($startString, $baseDate);
            $start = date("Y-m-d", $startTS);

            $return = [
                'value' => $startTS,
                'option' => 'GT',
                '1$filter' => $filter,
                '2$timestring' => $timeString,
                '3$target' => $baseDate,
                '4$target_clean' => date("Y-m-d", $baseDate),
                '5$startString' => $startString,
                '6$startDate' => $start,
                '7$startTS' => $startTS,
           ];
            //$this->logger->debug('parseFilter: '. json_encode($return));
        } else {
            $return = false;
        }
        return $return;
    }

    /**
     * parsing of ( ) format instructions
     *
     * @param $filter
     * @return string
     */
    private function parseFormat($filter) {
        preg_match_all("/(?<=\().*(?=\))/", $filter, $matches);
        if (count($matches[0]) > 0) {
            return $matches[0][0];
        } else {
            return 'Y-m-d H:m:s';
        }
    }
}