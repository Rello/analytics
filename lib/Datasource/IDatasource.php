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

declare(strict_types=1);

namespace OCA\Analytics\Datasource;
/**
 * Interface IDatasource
 *
 * @since 3.1.0
 */
interface IDatasource
{

    /**
     * @return string Display Name of the datasource
     * @since 3.1.0
     */
    public function getName(): string;

    /**
     * @return int 2 digit unique datasource id
     * @since 3.1.0
     */
    public function getId(): int;

    /**
     * get the available options of the datasoure
     *
     * return needs to be an array
     *  id      = name of the option for the readData
     *  name    = displayed name of the inputfield in the UI
     *  type    = 'tf' to create a dropdown if placeholder holds values separated with /
     *  placeholder = helptext for the inputfield in the UI
     *
     *  ['id' => 'datatype', 'name' => 'Type of Data', 'type' => 'tf', 'placeholder' => 'adaptation/absolute']
     *
     * @return array
     * @since 3.1.0
     */
    public function getTemplate(): array;

    /**
     * Read the Data
     *
     * return needs to be an array
     *  [
     *      'header' => $header,
     *      'data' => $data,
     *      'error' => 0,
     *  ]
     *
     * @param array
     * @return array available options of the datasoure
     * @since 3.1.0
     */
    public function readData($option): array;
}