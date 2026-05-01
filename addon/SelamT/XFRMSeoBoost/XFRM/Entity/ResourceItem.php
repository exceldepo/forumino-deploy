<?php

namespace SelamT\XFRMSeoBoost\XFRM\Entity;

class ResourceItem extends XFCP_ResourceItem
{
	/**
	 * XFRM'in ld+json çıktısını öne çıkan görsel, fiyat (offers), puan (aggregateRating)
	 * ve og_type'a göre @type değişikliği ile genişletir.
	 *
	 * Global option ile devre dışı bırakılabilir.
	 */
	public function getLdStructuredData(): array
	{
		$data = parent::getLdStructuredData();

		$options = \XF::options();
		if (empty($options->xfrmseoBoostEnableJsonLd))
		{
			return $data;
		}

		if (!isset($data['mainEntity']) || !is_array($data['mainEntity']))
		{
			return $data;
		}

		// Etkin og_type: resource override → category default → 'website'
		$ogType = $this->getEffectiveOgType();

		// 1) Featured image -> mainEntity.image
		$seoMeta = $this->SeoMeta;
		if ($seoMeta && $seoMeta->FeaturedImage)
		{
			$data['mainEntity']['image'] = $seoMeta->FeaturedImage->getDataUrl();
		}

		// 2) Price -> offers (varsa fiyat ve para birimi)
		if ((float) $this->price > 0 && $this->currency !== '')
		{
			$data['mainEntity']['offers'] = [
				'@type'         => 'Offer',
				'price'         => (string) $this->price,
				'priceCurrency' => (string) $this->currency,
				'availability'  => 'https://schema.org/InStock',
				'url'           => $this->getContentUrl(true),
			];
		}

		// 3) Rating -> aggregateRating
		if ((int) $this->rating_count > 0)
		{
			$data['mainEntity']['aggregateRating'] = [
				'@type'       => 'AggregateRating',
				'ratingValue' => round((float) $this->rating_avg, 1),
				'ratingCount' => (int) $this->rating_count,
				'bestRating'  => '5',
				'worstRating' => '1',
			];
		}

		// 4) Download count -> ek InteractionCounter
		if ((int) $this->download_count > 0)
		{
			$existingStats = $data['mainEntity']['interactionStatistic'] ?? [];
			$existingStats[] = [
				'@type'                => 'InteractionCounter',
				'interactionType'      => 'https://schema.org/DownloadAction',
				'userInteractionCount' => (int) $this->download_count,
			];
			$data['mainEntity']['interactionStatistic'] = $existingStats;
		}

		// 5) og:type=product ise @type'ı Product (veya SoftwareApplication) yap
		if (!empty($options->xfrmseoBoostEnableProductSchema) && $ogType === 'product')
		{
			$data['mainEntity']['@type'] = 'Product';
			// 'name' alanını ekle (Product şeması için zorunlu)
			$data['mainEntity']['name'] = $this->title;
		}
		elseif ($ogType === 'article')
		{
			$data['mainEntity']['@type'] = 'Article';
			$data['mainEntity']['headline'] = $data['mainEntity']['headline'] ?? $this->title;
		}

		return $data;
	}

	/**
	 * Efektif og_type: resource SeoMeta override → category SeoSettings.default_og_type → 'website'
	 */
	public function getEffectiveOgType(): string
	{
		$seoMeta = $this->SeoMeta;
		if ($seoMeta && $seoMeta->og_type !== '')
		{
			return $seoMeta->og_type;
		}

		$category = $this->Category;
		if ($category && $category->SeoSettings && $category->SeoSettings->default_og_type !== '')
		{
			return $category->SeoSettings->default_og_type;
		}

		return 'website';
	}
}
