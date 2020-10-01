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
 */
class Version020500Date20200815200000 extends SimpleMigrationStep
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
                'version' => $query->createNamedParameter('2.5.0'),
                'data' => $query->createNamedParameter('{"changelogURL":"https:\/\/github.com\/rello\/analytics\/blob\/master\/CHANGELOG.md","whatsNew":{
"en":{"regular":["Display your favorite reports in the new Dashboard (NC20)","Share reports with users or groups","More flexibility with the API (e.g. deletion of data)","Search integration (NC20)"],"admin":["New Features apply to users"]},
"de":{"regular":["Zeite Deine favorisierten Berichte im neuen Dashboard (NC20)","Teile Berichte mit User oder Gruppen","Flediblere API (z.B. Löschung von Daten)","Integration in Suche (NC20"],"admin":["Nur User Features"]}
}}'),
            ])
            ->execute();
    }
}
