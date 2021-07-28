<?php

declare(strict_types=1);

namespace OCA\Analytics\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 * sudo -u www-data php /var/www/nextcloud/occ migrations:execute analytics 3006Date20210601180000
 */
class Version3006Date20210601180000 extends SimpleMigrationStep
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

        if (!$schema->hasTable('analytics_report')) {
            $table = $schema->createTable('analytics_report');
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
            $table->addColumn('dataset', 'integer', [
                'notnull' => false,
            ]);
            $table->addColumn('visualization', 'string', [
                'notnull' => false,
                'length' => 10,
            ]);
            $table->addColumn('chart', 'string', [
                'notnull' => false,
                'length' => 256,
            ]);
            $table->addColumn('parent', 'integer', [
                'notnull' => false,
            ]);
            $table->addColumn('chartoptions', 'string', [
                'notnull' => false,
                'length' => 1000,
            ]);
            $table->addColumn('dataoptions', 'string', [
                'notnull' => false,
                'length' => 1000,
            ]);
            $table->addColumn('filteroptions', 'string', [
                'notnull' => false,
                'length' => 1000,
            ]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['dataset'], 'analytics_report_dataset_idx');
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
        $reportIdMap = [];

        $insert = $this->connection->getQueryBuilder();
        $insert->insert('analytics_report')
            ->values([
                'name' => $insert->createParameter('name'),
                'user_id' => $insert->createParameter('user_id'),
                'subheader' => $insert->createParameter('subheader'),
                'dataset' => $insert->createParameter('dataset'),
                'visualization' => $insert->createParameter('visualization'),
                'chart' => $insert->createParameter('chart'),
                'parent' => $insert->createParameter('parent'),
                'chartoptions' => $insert->createParameter('chartoptions'),
                'dataoptions' => $insert->createParameter('dataoptions'),
                'filteroptions' => $insert->createParameter('filteroptions'),
            ]);

        $query = $this->connection->getQueryBuilder();
        $query->select('*')
            ->from('analytics_dataset');

        $result = $query->execute();
        while ($row = $result->fetch()) {
            $insert
                ->setParameter('name', $row['name'])
                ->setParameter('user_id', $row['user_id'])
                ->setParameter('subheader', $row['subheader'])
                ->setParameter('dataset', (int)$row['id'], IQueryBuilder::PARAM_INT)
                ->setParameter('visualization', $row['visualization'])
                ->setParameter('chart', $row['chart'])
                ->setParameter('parent', (int)$row['parent'], IQueryBuilder::PARAM_INT)
                ->setParameter('chartoptions', $row['chartoptions'])
                ->setParameter('dataoptions', $row['dataoptions'])
                ->setParameter('filteroptions', $row['filteroptions']);
            $insert->execute();

            $reportIdMap[(int)$row['id']] = $insert->getLastInsertId();
        }
        $result->closeCursor();
    }
}
