<?php

namespace SelamT\XFRMSeoBoost;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;

class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUninstallTrait;
	use StepRunnerUpgradeTrait;

	// =============================================================
	// INSTALL — Yeni kurulumlar 1.1.0 schema'sıyla doğrudan kurulur.
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
	// UPGRADE — 1.0.0 → 1.1.0 schema migrasyonu
	// =============================================================

	public function upgrade1010070Step1(): void
	{
		$this->schemaManager()->alterTable('xf_st_xfrmseo_meta', function (Alter $table)
		{
			if (!$table->getColumnDefinition('display_image'))
			{
				$table->addColumn('display_image', 'tinyint')->setDefault(1);
			}
		});
	}

	public function upgrade1010070Step2(): void
	{
		if (!$this->tableExists('xf_st_xfrmseo_category'))
		{
			$this->schemaManager()->createTable('xf_st_xfrmseo_category', function (Create $table)
			{
				$table->addColumn('resource_category_id', 'int')->primaryKey();
				$table->addColumn('default_og_type', 'varchar', 50)->setDefault('');
			});
		}
	}

	// =============================================================
	// UPGRADE — 1.1.0 → 1.2.0 (manuel og_type override KALDIRILDI;
	// autoDetectOgType resource_type+price+rating üzerinden seçer)
	// =============================================================

	public function upgrade1020070Step1(): void
	{
		// xf_st_xfrmseo_meta.og_type kolonu kaldırılıyor — artık autoDetect kullanılıyor.
		$this->schemaManager()->alterTable('xf_st_xfrmseo_meta', function (Alter $table)
		{
			if ($table->getColumnDefinition('og_type'))
			{
				$table->dropColumns(['og_type']);
			}
		});
	}

	public function upgrade1020070Step2(): void
	{
		// xf_st_xfrmseo_category tablosu komple kaldırılıyor (default_og_type artık yok).
		if ($this->tableExists('xf_st_xfrmseo_category'))
		{
			$this->schemaManager()->dropTable('xf_st_xfrmseo_category');
		}
	}

	// =============================================================
	// UNINSTALL
	// =============================================================

	public function uninstallStep1(): void
	{
		$tables = [
			'xf_st_xfrmseo_meta',
			'xf_st_xfrmseo_image',
			'xf_st_xfrmseo_category', // 1.2.0 öncesinden kalmış olabilir
		];
		foreach ($tables as $table)
		{
			if ($this->tableExists($table))
			{
				$this->schemaManager()->dropTable($table);
			}
		}
	}

	// =============================================================
	// POST-INSTALL / POST-UPGRADE — phrase cache invalidation
	//
	// Yeni eklenen phrase'lerin (örn. v1.1.0'da gelen
	// xfrmseoBoost* option'ları) tüm dillerde anında görünmesi için
	// xf_language.phrase_cache binary blob'unu temizliyoruz.
	// Sonraki sayfa render'ında XF her dil için lazy olarak yeniden
	// compile eder, DB'deki en güncel TR/EN/diğer çevirileri okur.
	//
	// Kullanıcı manuel SQL çalıştırmak zorunda kalmasın diye burada.
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
