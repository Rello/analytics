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
class Version020400Date20200515185352 extends SimpleMigrationStep
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

        if (!$schema->hasTable('analytics_dataset')) {
            $table = $schema->createTable('analytics_dataset');
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
            $table->addColumn('link', 'string', [
                'notnull' => false,
                'length' => 256,
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
            $table->addColumn('chartoptions', 'string', [
                'notnull' => false,
                'length' => 1000,
            ]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['user_id'], 'analytics_dataset_user_id_idx');
        }

        if (!$schema->hasTable('analytics_facts')) {
            $table = $schema->createTable('analytics_facts');
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
            $table->addColumn('dimension1', 'string', [
                'notnull' => false,
                'length' => 64,
            ]);
            $table->addColumn('dimension2', 'string', [
                'notnull' => false,
                'length' => 64,
            ]);
            $table->addColumn('dimension3', 'decimal', [
                'notnull' => false,
            ]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['dataset'], 'analytics_facts_dataset_idx');
        }

        if (!$schema->hasTable('analytics_share')) {
            $table = $schema->createTable('analytics_share');
            $table->addColumn('id', 'integer', [
                'autoincrement' => true,
                'notnull' => true,
            ]);
            $table->addColumn('dataset', 'integer', [
                'notnull' => true,
            ]);
            $table->addColumn('type', 'integer', [
                'notnull' => false,
            ]);
            $table->addColumn('uid_owner', 'string', [
                'notnull' => false,
                'length' => 64,
            ]);
            $table->addColumn('uid_initiator', 'string', [
                'notnull' => true,
                'length' => 64,
            ]);
            $table->addColumn('permissions', 'integer', [
                'notnull' => false,
            ]);
            $table->addColumn('token', 'string', [
                'notnull' => false,
                'length' => 32,
            ]);
            $table->addColumn('password', 'string', [
                'notnull' => false,
                'length' => 64,
            ]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['dataset'], 'analytics_share_dataset_idx');
            $table->addIndex(['uid_owner'], 'analytics_share_uid_owner_idx');
            $table->addIndex(['token'], 'analytics_share_token_idx');
        }

        if (!$schema->hasTable('analytics_threshold')) {
            $table = $schema->createTable('analytics_threshold');
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
                'notnull' => false,
                'length' => 64,
            ]);
            $table->addColumn('dimension1', 'string', [
                'notnull' => false,
                'length' => 64,
            ]);
            $table->addColumn('dimension2', 'string', [
                'notnull' => false,
                'length' => 64,
            ]);
            $table->addColumn('dimension3', 'decimal', [
                'notnull' => false,
            ]);
            $table->addColumn('option', 'string', [
                'notnull' => false,
                'length' => 5,
            ]);
            $table->addColumn('severity', 'integer', [
                'notnull' => true,
            ]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['dataset'], 'analytics_threshold_dset_idx');
        }

        if (!$schema->hasTable('analytics_dataload')) {
            $table = $schema->createTable('analytics_dataload');
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
            $table->addColumn('datasource', 'integer', [
                'notnull' => true,
            ]);
            $table->addColumn('name', 'string', [
                'notnull' => false,
                'length' => 64,
            ]);
            $table->addColumn('option', 'string', [
                'notnull' => false,
                'length' => 500,
            ]);
            $table->addColumn('schedule', 'string', [
                'notnull' => false,
                'length' => 1,
            ]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['dataset'], 'analytics_dataload_dataset_idx');
        }

        if (!$schema->hasTable('analytics_whats_new')) {
            $table = $schema->createTable('analytics_whats_new');
            $table->addColumn('id', 'integer', [
                'autoincrement' => true,
                'notnull' => true,
                'length' => 4,
                'unsigned' => true,
            ]);
            $table->addColumn('version', 'string', [
                'notnull' => true,
                'length' => 64,
                'default' => '11',
            ]);
            $table->addColumn('etag', 'string', [
                'notnull' => true,
                'length' => 64,
                'default' => '',
            ]);
            $table->addColumn('last_check', 'integer', [
                'notnull' => true,
                'length' => 4,
                'unsigned' => true,
                'default' => 0,
            ]);
            $table->addColumn('data', 'text', [
                'notnull' => true,
                'default' => '',
            ]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['version'], 'analytics_whats_new_v_idx');
            $table->addIndex(['version', 'etag'], 'analytics_whats_new_v_e_idx');
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
        $query = $this->connection->getQueryBuilder();
        $query->insert('analytics_whats_new')
            ->values([
                'version' => $query->createNamedParameter('2.4.0'),
                'data' => $query->createNamedParameter('{"changelogURL":"https:\/\/github.com\/rello\/analytics\/blob\/master\/CHANGELOG.md","whatsNew":{
"en":{"regular":["Combined charts","Filter reports or change the drilldown (internal reports only)","Changes are persisted with the report","This WhatsNew popup"],"admin":["New Features apply to users"]},
"de":{"regular":["Kombinierte Diagramme","Filter oder Aufriss ändern (nur interne Berichte)","Änderungen werden im Report gespeichert","Dieses WhatsNew Fenster"],"admin":["Nur User Features"]}
}}'),
            ])
            ->execute();
    }
}
