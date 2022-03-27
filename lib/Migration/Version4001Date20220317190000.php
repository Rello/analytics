<?php

declare(strict_types=1);

namespace OCA\Analytics\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 * sudo -u www-data php /var/www/nextcloud/occ migrations:execute analytics 4001Date20220317190000
 *
 * Deletions
 * dataset:
 * subheader, link, visual, chart*, *options, parent, type
 */
class Version4001Date20220317190000 extends SimpleMigrationStep
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
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        $table = $schema->getTable('analytics_share');
        if (!$table->hasColumn('domain')) {
            $table->addColumn('domain', 'string', [
                'notnull' => false,
                'length' => 256,
            ]);
        }
        if ($table->hasIndex('analytics_share_dataset_idx')) {
            $table->dropIndex('analytics_share_dataset_idx');
        }
        if ($table->hasColumn('dataset')) {
            $table->dropColumn('dataset');
        }

        $table = $schema->getTable('analytics_threshold');
        if ($table->hasIndex('analytics_threshold_dset_idx')) {
            $table->dropIndex('analytics_threshold_dset_idx');
        }
        if ($table->hasColumn('dataset')) {
            $table->dropColumn('dataset');
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