<?php

declare(strict_types=1);

namespace OCA\Analytics\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 * sudo -u www-data php /var/www/nextcloud/occ migrations:execute analytics 4010Date20230815200000
 */
class Version4010Date20230815200000 extends SimpleMigrationStep
{
    public function __construct()
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

        $table = $schema->getTable('analytics_report');
        if (!$table->hasColumn('tableoptions')) {
            $table->addColumn('tableoptions', 'string', [
                'notnull' => false,
                'length' => 1000,
            ]);
        }
        return $schema;
    }
}