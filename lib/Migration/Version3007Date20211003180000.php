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
 * sudo -u www-data php /var/www/nextcloud/occ migrations:execute analytics 3007Date20211003180000
 *
 * Deletions
 * dataset:
 * subheader, link, visual, chart*, *options, parent, type
 */
class Version3007Date20211003180000 extends SimpleMigrationStep
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
            $table->addColumn('dataset', 'integer', [
                'notnull' => true,
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
            $table->addColumn('link', 'string', [
                'notnull' => false,
                'length' => 500,
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
            $table->addColumn('dimension1', 'string', [
                'notnull' => false,
                'length' => 64,
            ]);
            $table->addColumn('dimension2', 'string', [
                'notnull' => false,
                'length' => 64,
            ]);
            $table->addColumn('dimension3', 'string', [
                'notnull' => false,
                'length' => 64,
            ]);
            $table->addColumn('value', 'string', [
                'notnull' => false,
                'length' => 64,
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
            $table->addColumn('refresh', 'integer', [
                'notnull' => false,
            ]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['user_id'], 'analytics_report_user_id_idx');
        }

        $table = $schema->getTable('analytics_share');
        if (!$table->hasColumn('report')) {
            $table->addColumn('report', 'integer', [
                'notnull' => false,
            ]);
        }

        $table = $schema->getTable('analytics_threshold');
        if (!$table->hasColumn('report')) {
            $table->addColumn('report', 'integer', [
                'notnull' => false,
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
        $reportIdMap = $this->copyReport();
        $this->fixShares($reportIdMap);
        $this->fixThreshold($reportIdMap);
        $this->fixParents($reportIdMap);
    }

    /**
     * @return int[]
     */
    protected function copyReport(): array {
        $reportIdMap = [];

        $insert = $this->connection->getQueryBuilder();
        $insert->insert('analytics_report')
            ->values([
                'user_id' => $insert->createParameter('user_id'),
                'dataset' => $insert->createParameter('dataset'),
                'name' => $insert->createParameter('name'),
                'subheader' => $insert->createParameter('subheader'),
                'type' => $insert->createParameter('type'),
                'link' => $insert->createParameter('link'),
                'visualization' => $insert->createParameter('visualization'),
                'chart' => $insert->createParameter('chart'),
                'parent' => $insert->createParameter('parent'),
                'dimension1' => $insert->createParameter('dimension1'),
                'dimension2' => $insert->createParameter('dimension2'),
                'dimension3' => $insert->createParameter('dimension3'),
                'value' => $insert->createParameter('value'),
                'chartoptions' => $insert->createParameter('chartoptions'),
                'dataoptions' => $insert->createParameter('dataoptions'),
                'filteroptions' => $insert->createParameter('filteroptions'),
                'refresh' => $insert->createParameter('refresh'),
            ]);

        $query = $this->connection->getQueryBuilder();
        $query->select('*')
            ->from('analytics_dataset');

        $result = $query->execute();
        while ($row = $result->fetch()) {
            $insert
                ->setParameter('user_id', $row['user_id'])
                ->setParameter('dataset', (int) $row['id'], IQueryBuilder::PARAM_INT)
                ->setParameter('name', $row['name'])
                ->setParameter('subheader', $row['subheader'])
                ->setParameter('type', (int) $row['type'], IQueryBuilder::PARAM_INT)
                ->setParameter('link', $row['link'])
                ->setParameter('visualization', $row['visualization'])
                ->setParameter('chart', $row['chart'])
                ->setParameter('parent', (int) $row['parent'], IQueryBuilder::PARAM_INT)
                ->setParameter('dimension1', $row['dimension1'])
                ->setParameter('dimension2', $row['dimension2'])
                ->setParameter('dimension3', $row['dimension3'])
                ->setParameter('value', $row['value'])
                ->setParameter('chartoptions', $row['chartoptions'])
                ->setParameter('dataoptions', $row['dataoptions'])
                ->setParameter('filteroptions', $row['filteroptions'])
                ->setParameter('refresh', (int) $row['refresh'], IQueryBuilder::PARAM_INT);
            $insert->execute();

            $reportIdMap[(int)$row['id']] = $insert->getLastInsertId();
        }
        $result->closeCursor();

        return $reportIdMap;
    }

    /**
     * @param int[] $reportIdMap
     */
    protected function fixShares(array $reportIdMap): void {

        $update = $this->connection->getQueryBuilder();
        $update->update('analytics_share')
            ->set('report', $update->createParameter('report'))
            ->where($update->expr()->eq('dataset', $update->createParameter('dataset')));

        $query = $this->connection->getQueryBuilder();
        $query->select(['id'])
            ->from('analytics_dataset');
        $result = $query->execute();

        while ($row = $result->fetch()) {
            $update
                ->setParameter('report', $reportIdMap[(int) $row['id']])
                ->setParameter('dataset', (int) $row['id'])
            ;
            $update->execute();
        }
    }

    /**
     * @param int[] $reportIdMap
     */
    protected function fixThreshold(array $reportIdMap): void {

        $update = $this->connection->getQueryBuilder();
        $update->update('analytics_threshold')
            ->set('report', $update->createParameter('report'))
            ->where($update->expr()->eq('dataset', $update->createParameter('dataset')));

        $query = $this->connection->getQueryBuilder();
        $query->select(['id'])
            ->from('analytics_dataset');
        $result = $query->execute();

        while ($row = $result->fetch()) {
            $update
                ->setParameter('report', $reportIdMap[(int) $row['id']])
                ->setParameter('dataset', (int) $row['id'])
            ;
            $update->execute();
        }
    }

    /**
     * @param int[] $reportIdMap
     */
    protected function fixParents(array $reportIdMap): void {

        $update = $this->connection->getQueryBuilder();
        $update->update('analytics_report')
            ->set('parent', $update->createParameter('parent_to'))
            ->where($update->expr()->eq('id', $update->createParameter('id')));

        $query = $this->connection->getQueryBuilder();
        $query->select(['id', 'parent'])
            ->from('analytics_report')
            ->where($query->expr()->neq('parent', $query->createNamedParameter('0')));
        $result = $query->execute();

        while ($row = $result->fetch()) {
            $update
                ->setParameter('parent_to', $reportIdMap[(int) $row['parent']])
                ->setParameter('id', (int) $row['id'])
            ;
            $update->execute();
        }
    }

}