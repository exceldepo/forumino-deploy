<?php

namespace SelamT\XFRMSeoBoost\Service\Meta;

use SelamT\XFRMSeoBoost\Entity\ResourceMeta;
use XF\Service\AbstractService;
use XFRM\Entity\ResourceItem;

class Saver extends AbstractService
{
	protected ResourceItem $resource;
	protected ResourceMeta $meta;
	protected array $errors = [];

	public function __construct(\XF\App $app, ResourceItem $resource)
	{
		parent::__construct($app);
		$this->resource = $resource;

		// Önce Resource entity'sinin SeoMeta relation'ından dene (cache'de varsa hızlı).
		$existing = $resource->SeoMeta;

		// Cache stale olabilir (yeni save sonrası); DB'den explicit fetch yap.
		if (!($existing instanceof ResourceMeta))
		{
			/** @var ResourceMeta|null $existing */
			$existing = $this->em()->find('SelamT\XFRMSeoBoost:ResourceMeta', $resource->resource_id);
		}

		if ($existing instanceof ResourceMeta)
		{
			$this->meta = $existing;
		}
		else
		{
			/** @var ResourceMeta $meta */
			$meta = $this->em()->create('SelamT\XFRMSeoBoost:ResourceMeta');
			$meta->resource_id = $resource->resource_id;
			$this->meta = $meta;
		}
	}

	public function setMetaTitle(string $value): self
	{
		$this->meta->meta_title = $this->trim($value, 200);
		return $this;
	}

	public function setMetaDescription(string $value): self
	{
		$this->meta->meta_description = $this->trim($value, 500);
		return $this;
	}

	public function setFocusKeyword(string $value): self
	{
		$this->meta->focus_keyword = $this->trim($value, 100);
		return $this;
	}

	public function setCanonicalUrl(string $value): self
	{
		$value = $this->trim($value, 500);
		// Geçersiz URL varsa boşalt — boş kabul edilir, default canonical kullanılır.
		if ($value !== '' && !filter_var($value, FILTER_VALIDATE_URL))
		{
			$value = '';
		}
		$this->meta->canonical_url = $value;
		return $this;
	}

	public function setDisplayImage(bool $value): self
	{
		$this->meta->display_image = $value;
		return $this;
	}

	public function setFromInput(array $input): self
	{
		if (array_key_exists('meta_title', $input))       { $this->setMetaTitle((string) $input['meta_title']); }
		if (array_key_exists('meta_description', $input)) { $this->setMetaDescription((string) $input['meta_description']); }
		if (array_key_exists('focus_keyword', $input))    { $this->setFocusKeyword((string) $input['focus_keyword']); }
		if (array_key_exists('canonical_url', $input))    { $this->setCanonicalUrl((string) $input['canonical_url']); }
		if (array_key_exists('display_image', $input))    { $this->setDisplayImage((bool) $input['display_image']); }
		return $this;
	}

	public function save(): ResourceMeta
	{
		// Yeni entity + tüm alanlar boşsa: gereksiz kayıt yapmayalım.
		// (Mevcut entity her durumda save edilir; alanlar boş olsa bile entity korunur,
		//  çünkü display_image=1, image_id, focus_keyword vs. partial update'leri olabilir.)
		if ($this->isAllEmpty() && !$this->meta->exists())
		{
			return $this->meta;
		}

		$this->meta->save();
		return $this->meta;
	}

	public function getMeta(): ResourceMeta
	{
		return $this->meta;
	}

	protected function isAllEmpty(): bool
	{
		return $this->meta->meta_title === ''
			&& $this->meta->meta_description === ''
			&& $this->meta->focus_keyword === ''
			&& $this->meta->canonical_url === ''
			&& (int) $this->meta->image_id === 0
			&& (bool) $this->meta->display_image === true; // varsayılan değer = boş kabul et
	}

	protected function trim(string $value, int $maxLen): string
	{
		$value = trim($value);
		if ($value === '')
		{
			return '';
		}
		// utf8 safe substr
		if (function_exists('mb_substr'))
		{
			return mb_substr($value, 0, $maxLen, 'UTF-8');
		}
		return substr($value, 0, $maxLen);
	}
}
