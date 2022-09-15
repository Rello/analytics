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
 * sudo -u www-data php /var/www/nextcloud/occ migrations:execute analytics 4004Date20220915190000
 */
class Version4004Date20220915190000 extends SimpleMigrationStep
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
    }

    /**
     * @param IOutput $output
     * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
     * @param array $options
     */
    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options)
    {
        $query = $this->connection->getQueryBuilder();
        $query->insert('analytics_whats_new')
            ->values([
                'version' => $query->createNamedParameter('4.4.0'),
                'data' => $query->createNamedParameter('{"changelogURL":"https:\/\/github.com\/rello\/analytics\/blob\/master\/CHANGELOG.md","whatsNew":{
"en":{"regular":["Organize reports and folders with drag & drop","Textvariables in thresholds","Various fixes"],"admin":["New Features apply to users"]},
"de":{"regular":["Organisiere Berichte und Gruppen durch drag & drop","Textvariablen in Schwellwerten","Verschiedene Korrekturen"],"admin":["Nur User Features"]}
}}'),
            ])
            ->execute();
    }
}
