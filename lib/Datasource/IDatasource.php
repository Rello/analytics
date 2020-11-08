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
     * @return array available options of the datasoure
     * @since 3.1.0
     */
    public function getTemplate(): array;

    /**
     * Read the Data
     * @param array $option
     * @return array available options of the datasoure
     * @since 3.1.0
     */
    public function readData($option): array;
}