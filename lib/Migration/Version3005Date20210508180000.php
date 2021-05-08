<?php

declare(strict_types=1);

namespace OCA\Analytics\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 * sudo -u www-data php /var/www/nextcloud/occ migrations:execute analytics 3005Date20210508180000
 */
class Version3005Date20210508180000 extends SimpleMigrationStep
{

    /** @var IDBConnection */
    private $connection;

    public function __construct(IDBConnection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param IOutput $output
     * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
     * @param array $options
     */
    public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options)
    {
    }

    /**
     * @param IOutput $output
     * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
     * @param array $options
     * @return null|ISchemaWrapper
     */
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options)
    {
        /** @var Schema $schema */
        $schema = $schemaClosure();

        $table = $schema->getTable('analytics_facts');
        if (!$table->hasColumn('value')) {
            $table->addColumn('value', 'decimal', [
                'notnull' => false,
                'precision' => 15,
                'scale' => 2,
                'default' => 0,
            ]);
        }
        if (!$table->hasColumn('timestamp')) {
            $table->addColumn('timestamp', 'integer', [
                'notnull' => false,
                'default' => 0,
            ]);
        }

        $table = $schema->getTable('analytics_dataset');
        if (!$table->hasColumn('value')) {
            $table->addColumn('value', 'string', [
                'notnull' => false,
                'length' => 64,
            ]);
        }
        $column = $table->getColumn('link');
        if ($column->getLength() !== 500) {
            $column->setLength(500);
        }

        $table = $schema->getTable('analytics_threshold');
        if (!$table->hasColumn('value')) {
            $table->addColumn('value', 'decimal', [
                'notnull' => false,
                'precision' => 15,
                'scale' => 2,
                'default' => 0,
            ]);
        }

        return $schema;
    }

    /**
     * @param IOutput $output
     * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
     * @param array $options
     */
    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options)
    {
    }
}
