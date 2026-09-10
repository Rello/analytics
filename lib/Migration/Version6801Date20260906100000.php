<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Analytics\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version6801Date20260906100000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$dataset = $schema->getTable('analytics_dataset');
		if (!$dataset->hasColumn('storage_mode')) {
			$dataset->addColumn('storage_mode', Types::STRING, [
				'notnull' => true,
				'length' => 32,
				'default' => 'legacy',
			]);
		}
		if (!$dataset->hasColumn('schema_version')) {
			$dataset->addColumn('schema_version', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);
		}

		$dataload = $schema->getTable('analytics_dataload');
		if (!$dataload->hasColumn('storage_mapping')) {
			$dataload->addColumn('storage_mapping', Types::TEXT, [
				'notnull' => false,
			]);
		}

		$threshold = $schema->getTable('analytics_threshold');
		if (!$threshold->hasColumn('source_column_ref')) {
			$threshold->addColumn('source_column_ref', Types::STRING, [
				'notnull' => false,
				'length' => 32,
			]);
		}

		if (!$schema->hasTable('analytics_flex_columns')) {
			$columns = $schema->createTable('analytics_flex_columns');
			$columns->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$columns->addColumn('dataset_id', Types::INTEGER, ['notnull' => true]);
			$columns->addColumn('name', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$columns->addColumn('logical_type', Types::STRING, [
				'notnull' => true,
				'length' => 16,
			]);
			$columns->addColumn('column_role', Types::STRING, [
				'notnull' => true,
				'length' => 16,
			]);
			$columns->addColumn('display_position', Types::INTEGER, ['notnull' => true]);
			$columns->addColumn('nullable_flag', Types::SMALLINT, [
				'notnull' => true,
				'default' => 1,
			]);
			$columns->addColumn('default_aggregation', Types::STRING, [
				'notnull' => false,
				'length' => 20,
			]);
			$columns->setPrimaryKey(['id'], 'a_col_pk');
			$columns->addIndex(['dataset_id', 'display_position'], 'a_col_ds_pos_idx');
		}

		if (!$schema->hasTable('analytics_flex_records')) {
			$records = $schema->createTable('analytics_flex_records');
			$records->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$records->addColumn('dataset_id', Types::INTEGER, ['notnull' => true]);
			$records->addColumn('dimension_key', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$records->addColumn('updated_at', Types::DATETIME, ['notnull' => true]);
			$records->setPrimaryKey(['id'], 'a_rec_pk');
			$records->addUniqueIndex(['dataset_id', 'dimension_key'], 'a_rec_ds_key_uniq');
			$records->addIndex(['dataset_id', 'id'], 'a_rec_ds_id_idx');
		}

		if (!$schema->hasTable('analytics_flex_values')) {
			$values = $schema->createTable('analytics_flex_values');
			$values->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$values->addColumn('dataset_id', Types::INTEGER, ['notnull' => true]);
			$values->addColumn('record_id', Types::BIGINT, ['notnull' => true]);
			$values->addColumn('column_id', Types::INTEGER, ['notnull' => true]);
			$values->addColumn('text_value', Types::TEXT, ['notnull' => false]);
			$values->addColumn('decimal_value', Types::DECIMAL, [
				'notnull' => false,
				'precision' => 30,
				'scale' => 10,
			]);
			$values->addColumn('datetime_value', Types::DATETIME, ['notnull' => false]);
			$values->setPrimaryKey(['id'], 'a_val_pk');
			$values->addUniqueIndex(['dataset_id', 'record_id', 'column_id'], 'a_val_ds_rec_col_uniq');
			$values->addIndex(['dataset_id', 'column_id', 'decimal_value'], 'a_val_col_dec_idx');
			$values->addIndex(['dataset_id', 'column_id', 'datetime_value'], 'a_val_col_dt_idx');
		}

		return $schema;
	}
}
