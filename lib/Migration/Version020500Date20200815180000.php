<?php

declare(strict_types=1);

namespace OCA\Analytics\Migration;

use Closure;
use Doctrine\DBAL\Types\Type;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 * sudo -u www-data php /var/www/nextcloud/occ migrations:execute analytics 020500Date20200815180000
 */
class Version020500Date20200815180000 extends SimpleMigrationStep
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

        $table->addColumn('value', Type::DECIMAL, [
            'notnull' => false,
            'precision' => 15,
            'scale' => 2,
            'default' => 0,
        ]);

        $table->addColumn('timestamp', Type::INTEGER, [
            'notnull' => false,
            'default' => 0,
        ]);


        $table = $schema->getTable('analytics_dataset');

        $table->addColumn('value', Type::STRING, [
            'notnull' => false,
            'length' => 64,
        ]);

        return $schema;
    }

    /**
     * @param IOutput $output
     * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
     * @param array $options
     */
    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options)
    {
        $query = $this->connection->getQueryBuilder();
        $query->update('analytics_facts')
            ->set('value', 'dimension3');
        $query->execute();

        $query2 = $this->connection->getQueryBuilder();
        $query2->update('analytics_dataset')
            ->set('value', 'dimension3');
        $query2->execute();
    }
}
