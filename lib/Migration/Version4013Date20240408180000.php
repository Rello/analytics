<?php

declare(strict_types=1);

namespace OCA\Analytics\Migration;

use Closure;
use Doctrine\DBAL\Types\Type;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 * sudo -u www-data php occ migrations:execute analytics 4013Date20240408180000
 */
class Version4013Date20240408180000 extends SimpleMigrationStep
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

        // Change Type of Description from string to text. Necessary due to length restrictions.
        $column = $schema->getTable('analytics_dataload')->getColumn('option');
        if ($column->getType() === Type::getType(Types::STRING)) {
            $column->setType(Type::getType(Types::TEXT));
        }

        return $schema;
    }
}