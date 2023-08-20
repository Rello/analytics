<?php

declare(strict_types=1);

namespace OCA\Analytics\Migration;

use Closure;
use OCP\DB\Exception;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 * sudo -u www-data php /var/www/nextcloud/occ migrations:execute analytics 4009Date20230517190000
 */
class Version4010Date20230815190000 extends SimpleMigrationStep
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
     * @throws Exception
     */
    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options)
    {
        $query = $this->connection->getQueryBuilder();
        $query->insert('analytics_whats_new')
            ->values([
                'version' => $query->createNamedParameter('4.10.0'),
                'data' => $query->createNamedParameter('{"changelogURL":"https:\/\/github.com\/rello\/analytics\/blob\/master\/CHANGELOG.md","whatsNew":{
"en":{"regular":["Translation integration","Custom columns in column picker","List available values for input boxes","Table enhancements"],"admin":["New Features apply to users"]},
"de":{"regular":["Translation Integration","Benutzerdefinierte Spalten im Column Picker","Eingabehilfe in Formularfeldern","Tabellen Verbesserungen"],"admin":["Nur User Features"]}
}}'),
            ])
            ->executeStatement();
    }
}