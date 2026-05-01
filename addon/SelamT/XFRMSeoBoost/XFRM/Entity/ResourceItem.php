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

		// 5) Schema @type seçimi (akıllı: og:type + Google "rich result için zorunlu alan" gereksinimleri):
		//    - Article: og_type=article → @type=Article (her zaman geçerli — zorunlu offers/rating yok)
		//    - Product: og_type=product VE (price>0 OR rating_count>0) → @type=Product
		//      (offers veya aggregateRating zorunlu alanları sağlanır; aksi halde Google "invalid" der)
		//    - Diğer: parent default (CreativeWork) bırakılır
		$enableProduct = !empty($options->xfrmseoBoostEnableProductSchema);
		$hasOffers = (float) $this->price > 0 && $this->currency !== '';
		$hasRating = (int) $this->rating_count > 0;

		if ($ogType === 'article')
		{
			$data['mainEntity']['@type'] = 'Article';
			$data['mainEntity']['headline'] = $data['mainEntity']['headline'] ?? $this->title;
			$data['mainEntity']['datePublished'] = $data['mainEntity']['dateCreated'] ?? \XF::language()->dateTime($this->resource_date, 'Y-m-d\TH:i:sP');
		}
		elseif ($enableProduct && $ogType === 'product' && ($hasOffers || $hasRating))
		{
			$data['mainEntity']['@type'] = 'Product';
			$data['mainEntity']['name'] = $this->title;
		}
		// Aksi halde XFRM default @type=CreativeWork olarak kalır (her zaman geçerli)

		return $data;
	}

	/**
	 * Efektif og_type seçimi:
	 *   1. Resource SeoMeta.og_type override (kullanıcı manuel seçti)
	 *   2. Category SeoSettings.default_og_type
	 *   3. Otomatik tespit (resource_type + price'a göre)
	 */
	public function getEffectiveOgType(): string
	{
		// 1. Resource manual override
		$seoMeta = $this->SeoMeta;
		if ($seoMeta && $seoMeta->og_type !== '')
		{
			return $seoMeta->og_type;
		}

		// 2. Category default
		$category = $this->Category;
		if ($category && $category->SeoSettings && $category->SeoSettings->default_og_type !== '')
		{
			return $category->SeoSettings->default_og_type;
		}

		// 3. Otomatik
		return $this->autoDetectOgType();
	}

	/**
	 * Otomatik og_type tespiti — resource_type ve price'a göre:
	 *   - fileless (dosyasız makale)        → article
	 *   - external_purchase / price > 0     → product
	 *   - download (ücretsiz)                → website (default — CreativeWork kalır)
	 */
	protected function autoDetectOgType(): string
	{
		$type = (string) $this->resource_type;

		if ($type === 'fileless')
		{
			return 'article';
		}

		if ($type === 'external_purchase' || (float) $this->price > 0)
		{
			return 'product';
		}

		// Ücretsiz indirilebilir kaynak — XFRM default'u (CreativeWork) doğru çalışır
		return 'website';
	}
}
