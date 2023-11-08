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
 * sudo -u www-data php /var/www/nextcloud/occ migrations:execute analytics 4012Date20231108180000
 */
class Version4012Date20231108180000 extends SimpleMigrationStep
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
     * @return null|ISchemaWrapper
     */
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options)
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('analytics_story')) {
            $table = $schema->createTable('analytics_story');
            $table->addColumn('id', 'integer', [
                'autoincrement' => true,
                'notnull' => true,
            ]);
            $table->addColumn('user_id', 'string', [
                'notnull' => true,
                'length' => 64,
            ]);
            $table->addColumn('name', 'string', [
                'notnull' => true,
                'length' => 64,
            ]);
            $table->addColumn('subheader', 'string', [
                'notnull' => false,
                'length' => 256,
            ]);
            $table->addColumn('type', 'integer', [
                'notnull' => false,
            ]);
            $table->addColumn('page', 'integer', [
                'notnull' => false,
            ]);
            $table->addColumn('parent', 'integer', [
                'notnull' => false,
            ]);
            $table->addColumn('reports', 'string', [
                'notnull' => false,
                'length' => 256,
            ]);
            $table->addColumn('layout', 'string', [
                'notnull' => false,
                'length' => 1000,
            ]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['user_id'], 'analytics_dataset_user_id_idx');
        }
        return $schema;
    }
}
