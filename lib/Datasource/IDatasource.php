<?php

declare(strict_types=1);

namespace OCA\Analytics\Datasource;
/**
 * Interface IWidget
 *
 * @since 20.0.0
 */
interface IDatasource
{

    /**
     * @return string Name
     * @since 20.0.0
     */
    public function getName(): string;

    /**
     * @return array available options of the datasoure
     * @since 20.0.0
     */
    public function getTemplates(): array;

    /**
     * Read the Data
     * @since 20.0.0
     */
    public function readData(): string;
}