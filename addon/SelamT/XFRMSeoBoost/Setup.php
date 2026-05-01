<?php

namespace SelamT\XFRMSeoBoost;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Create;

class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUninstallTrait;
	use StepRunnerUpgradeTrait;

	// =============================================================
	// INSTALL — v1.0.0 schema
	// =============================================================

	public function installStep1(): void
	{
		$this->schemaManager()->createTable('xf_st_xfrmseo_meta', function (Create $table)
		{
			$table->addColumn('resource_id', 'int')->primaryKey();
			$table->addColumn('meta_title', 'varchar', 200)->setDefault('');
			$table->addColumn('meta_description', 'varchar', 500)->setDefault('');
			$table->addColumn('focus_keyword', 'varchar', 100)->setDefault('');
			$table->addColumn('canonical_url', 'varchar', 500)->setDefault('');
			$table->addColumn('image_id', 'int')->setDefault(0);
			$table->addColumn('display_image', 'tinyint')->setDefault(1);
			$table->addKey('image_id');
		});
	}

	public function installStep2(): void
	{
		$this->schemaManager()->createTable('xf_st_xfrmseo_image', function (Create $table)
		{
			$table->addColumn('image_id', 'int')->autoIncrement();
			$table->addColumn('resource_id', 'int');
			$table->addColumn('file_name', 'varchar', 255);
			$table->addColumn('file_hash', 'varchar', 32);
			$table->addColumn('file_size', 'int');
			$table->addColumn('width', 'smallint')->setDefault(0);
			$table->addColumn('height', 'smallint')->setDefault(0);
			$table->addColumn('upload_date', 'int');
			$table->addKey('resource_id');
		});
	}

	// =============================================================
	// UNINSTALL
	// =============================================================

	public function uninstallStep1(): void
	{
		$tables = ['xf_st_xfrmseo_meta', 'xf_st_xfrmseo_image'];
		foreach ($tables as $table)
		{
			if ($this->tableExists($table))
			{
				$this->schemaManager()->dropTable($table);
			}
		}
	}

	// =============================================================
	// POST-INSTALL — phrase cache invalidation
	// =============================================================

	public function postInstall(array &$stateChanges): void
	{
		$this->invalidatePhraseCache();
	}

	public function postUpgrade($previousVersion, array &$stateChanges): void
	{
		$this->invalidatePhraseCache();
	}

	protected function invalidatePhraseCache(): void
	{
		$this->db()->update('xf_language', ['phrase_cache' => ''], null);
		$this->db()->emptyTable('xf_phrase_compiled');
	}
}
