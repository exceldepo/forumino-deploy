<?php

namespace SelamT\XFRMSeoBoost\Cli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use XF\Cli\Command\AddOnActionInterface;
use XF\Entity\Language;
use XF\Service\Phrase\ImportService;

class ImportTranslations extends Command
{
	protected function configure()
	{
		$this
			->setName('selamt:import-translations')
			->setDescription('Toplu dil çevirisi import (her addon için _translations/<AddOnId>/phrases.xml)')
			->addOption('source', 's', InputOption::VALUE_REQUIRED, 'Source root (default: _translations/ at web root)')
			->addOption('language', 'l', InputOption::VALUE_REQUIRED, 'Language ISO code', 'tr')
			->addOption('language-id', null, InputOption::VALUE_REQUIRED, 'Language ID (overrides --language lookup)');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$source = $input->getOption('source') ?: \XF::getRootDirectory() . DIRECTORY_SEPARATOR . '_translations';
		$source = rtrim($source, '/\\');

		if (!is_dir($source))
		{
			$output->writeln("<error>Source dizini bulunamadı: $source</error>");
			return 1;
		}

		$language = $this->resolveLanguage($input, $output);
		if (!$language)
		{
			return 1;
		}

		$output->writeln("<info>Hedef dil: {$language->title} (ID: {$language->language_id})</info>");
		$output->writeln("<info>Source: $source</info>");
		$output->writeln('');

		$dirs = glob($source . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
		if (!$dirs)
		{
			$output->writeln('<comment>Source altında addon klasörü yok.</comment>');
			return 0;
		}

		$totalAddons = 0;
		$totalPhrases = 0;

		foreach ($dirs AS $dir)
		{
			$addOnIdRaw = basename($dir);
			$xmlPath = $dir . DIRECTORY_SEPARATOR . 'phrases.xml';

			if (!file_exists($xmlPath))
			{
				$output->writeln("<comment>Atlandı (XML yok): $addOnIdRaw</comment>");
				continue;
			}

			$addOnId = str_replace('_', '/', $addOnIdRaw);
			if (substr_count($addOnId, '/') === 0)
			{
				$addOnId = $addOnIdRaw;
			}

			$xml = simplexml_load_file($xmlPath);
			if (!$xml)
			{
				$output->writeln("<error>XML parse hatası: $xmlPath</error>");
				continue;
			}

			$count = isset($xml->phrase) ? count($xml->phrase) : 0;
			$languageId = $language->language_id;
			$db = \XF::db();

			// 1) Bu addon'a ait eski TR phrase'leri (boş addon_id'ler dahil) temizle.
			//    XML'de phrase'lerde addon_id attribute yok (manual export); ImportService
			//    deleteExistingPhrases() addon_id ile filtre yapıyor, boş addon_id
			//    geçersek tüm dilin boş addon'lı phrase'lerini siler — istemediğimiz şey.
			//    O yüzden title-bazlı temizlik yapıyoruz.
			$titles = [];
			foreach ($xml->phrase AS $p)
			{
				$titles[] = (string) $p['title'];
			}
			if ($titles)
			{
				$db->delete('xf_phrase',
					'language_id = ? AND title IN (' . $db->quote($titles) . ')',
					[$languageId]
				);
			}

			// 2) ImportService ile yeni phrase'leri ekle (addon_id boş olarak gelir).
			/** @var ImportService $importer */
			$importer = \XF::app()->service(ImportService::class, $language);
			$importer->importFromXml($xml, $addOnId);

			// 3) Yeni oluşan boş addon_id phrase'leri bu addon'a bağla.
			if ($addOnId !== '')
			{
				$db->update('xf_phrase',
					['addon_id' => $addOnId],
					'language_id = ? AND addon_id = ? AND title IN (' . $db->quote($titles) . ')',
					[$languageId, '']
				);
			}

			$output->writeln(sprintf('<info>✓</info> %s — %d phrase (addon_id: %s)', $addOnIdRaw, $count, $addOnId));
			$totalAddons++;
			$totalPhrases += $count;
		}

		$output->writeln('');
		$output->writeln(sprintf('<info>Tamamlandı: %d addon, %d phrase import edildi.</info>', $totalAddons, $totalPhrases));
		$output->writeln('<comment>Not: phrase rebuild job arka planda çalışır. `php cmd.php xf:run-jobs` ile hızlandır.</comment>');

		return 0;
	}

	protected function resolveLanguage(InputInterface $input, OutputInterface $output): ?Language
	{
		$em = \XF::app()->em();

		$languageId = $input->getOption('language-id');
		if ($languageId)
		{
			$language = $em->find(Language::class, (int) $languageId);
			if (!$language)
			{
				$output->writeln("<error>Language ID bulunamadı: $languageId</error>");
				return null;
			}
			return $language;
		}

		$iso = $input->getOption('language');
		$isTr = ($iso === 'tr');
		// XF locale standardı: tr → tr-TR, en → en-US
		$canonicalCode = $isTr ? 'tr-TR' : ($iso === 'en' ? 'en-US' : $iso);

		// tr veya tr-TR ile bul
		$language = $em->findOne(Language::class, ['language_code' => $canonicalCode]);
		if (!$language)
		{
			$language = $em->findOne(Language::class, ['language_code' => $iso]);
		}

		if (!$language)
		{
			$output->writeln("<comment>'{$iso}' dili yok, oluşturuluyor...</comment>");
			$language = $em->create(Language::class);
			$language->title = $isTr ? 'Türkçe' : strtoupper($iso);
			$language->language_code = $canonicalCode;
			$language->date_format = $isTr ? 'j M Y' : 'M j, Y';
			$language->date_short_format = $isTr ? 'd.m.Y' : 'M j, Y';
			$language->date_short_recent_format = $isTr ? 'j M' : 'M j';
			$language->time_format = $isTr ? 'H:i' : 'g:i A';
			$language->currency_format = $isTr ? '%symbol%%amount%' : '%symbol%%amount%';
			$language->parent_id = 0;
			$language->save();
		}
		elseif ($language->language_code !== $canonicalCode)
		{
			// Mevcut dilin kodu non-canonical (örn: 'tr' → 'tr-TR')
			$language->language_code = $canonicalCode;
			$language->save();
			$output->writeln("<comment>language_code '{$canonicalCode}' olarak güncellendi.</comment>");
		}

		return $language;
	}
}
