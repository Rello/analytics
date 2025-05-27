<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Analytics\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version4012Date20240101000000 extends SimpleMigrationStep
{
    /** @var IDBConnection */
    private $connection;

    public function __construct(IDBConnection $connection)
    {
        $this->connection = $connection;
    }

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options)
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        return $schema;
    }

    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options)
    {
        $qb = $this->connection->getQueryBuilder();
        $qb->update('analytics_threshold')
            ->set('dimension2', $qb->createNamedParameter('0'))
            ->where($qb->expr()->isNull('dimension2'))
            ->executeStatement();
    }
}
