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
		$this->importTemplatesFromXml();
		$this->bumpStyleCacheVersion();
	}

	public function postUpgrade($previousVersion, array &$stateChanges): void
	{
		$this->invalidatePhraseCache();
		$this->importTemplatesFromXml();
		$this->bumpStyleCacheVersion();
	}

	protected function invalidatePhraseCache(): void
	{
		$this->db()->update('xf_language', ['phrase_cache' => ''], null);
		$this->db()->emptyTable('xf_phrase_compiled');
	}

	/**
	 * XF default xf-addon:install komutu _data/templates.xml dosyasını import etmiyor —
	 * sadece template_modifications, options, phrases vb import ediyor. Bu yüzden
	 * eklentideki LESS dosyalarını (xf_template tablosuna) manuel ekliyoruz ki kullanıcı
	 * fresh install'da tüm CSS'lerimiz çalışsın.
	 */
	protected function importTemplatesFromXml(): void
	{
		$xmlPath = $this->addOn->getAddOnDirectory() . '/_data/templates.xml';
		if (!file_exists($xmlPath))
		{
			return;
		}

		$xml = @simplexml_load_file($xmlPath);
		if (!$xml || !isset($xml->template))
		{
			return;
		}

		$em = \XF::em();
		foreach ($xml->template AS $node)
		{
			$title = (string) $node['title'];
			$type = (string) $node['type'];
			$content = (string) $node;

			if ($title === '' || $type === '')
			{
				continue;
			}

			$existing = $em->findOne('XF:Template', [
				'title' => $title,
				'type' => $type,
				'style_id' => 0,
			]);

			if ($existing)
			{
				$existing->bulkSet([
					'template' => $content,
					'version_id' => (int) $node['version_id'],
					'version_string' => (string) $node['version_string'],
					'addon_id' => $this->addOn->getAddOnId(),
				]);
				$existing->save();
				continue;
			}

			$template = $em->create('XF:Template');
			$template->bulkSet([
				'type' => $type,
				'title' => $title,
				'style_id' => 0,
				'template' => $content,
				'addon_id' => $this->addOn->getAddOnId(),
				'version_id' => (int) $node['version_id'],
				'version_string' => (string) $node['version_string'],
			]);
			$template->save();
		}
	}

	/**
	 * CSS bundle URL'sindeki "d=<timestamp>" cache buster'ı yenilemek için xf_style
	 * last_modified_date alanını update ediyoruz. Aksi halde browser eski CSS'i kullanır,
	 * yeni LESS değişiklikleri ekrana yansımaz.
	 */
	protected function bumpStyleCacheVersion(): void
	{
		$this->db()->update('xf_style', ['last_modified_date' => \XF::$time], null);
	}
}
