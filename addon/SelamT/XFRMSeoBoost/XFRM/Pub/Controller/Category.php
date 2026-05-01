<?php

namespace SelamT\XFRMSeoBoost\XFRM\Pub\Controller;

use SelamT\XFRMSeoBoost\Service\Image\Uploader;
use SelamT\XFRMSeoBoost\Service\Meta\Saver;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Redirect;
use XFRM\Entity\ResourceItem;

class Category extends XFCP_Category
{
	public function actionAdd(ParameterBag $params)
	{
		$reply = parent::actionAdd($params);

		// Sadece POST + başarılı redirect durumunda SEO meta kaydet.
		// (parent metod yeni resource'u oluşturur, sonra resources/{id}'ye redirect eder.)
		if ($this->isPost() && $reply instanceof Redirect)
		{
			$resource = $this->resolveCreatedResource($reply);
			if ($resource)
			{
				$this->seoMetaSaveFromRequest($resource);
			}
		}

		return $reply;
	}

	/**
	 * Redirect URL'inden yeni oluşan resource_id'yi yakala.
	 * URL pattern: /resources/example.123/
	 */
	protected function resolveCreatedResource(Redirect $reply): ?ResourceItem
	{
		$url = $reply->getUrl();
		if (!$url)
		{
			return null;
		}

		// Sadece path kısmını al (query string varsa hariç tut), sonra resource_id'yi yakala.
		$path = parse_url($url, PHP_URL_PATH) ?: '';
		// /resources/<title>.<id>/ veya /resources/<id>/ pattern
		if (!preg_match('~/resources/(?:[^/]*\.)?(\d+)/?$~', $path, $m))
		{
			return null;
		}

		$resourceId = (int) $m[1];
		if ($resourceId <= 0)
		{
			return null;
		}

		/** @var ResourceItem|null $resource */
		$resource = $this->em()->find('XFRM:ResourceItem', $resourceId, ['SeoMeta']);
		return $resource;
	}

	/**
	 * SEO meta + featured image upload (form input'tan).
	 * ResourceItem::seoMetaSaveFromRequest() ile aynı logic — DRY için Service'e taşınabilir ileride.
	 */
	protected function seoMetaSaveFromRequest(ResourceItem $resource): void
	{
		$input = $this->filter('selamt_xfrmseo', [
			'meta_title'       => 'str',
			'meta_description' => 'str',
			'focus_keyword'    => 'str',
			'canonical_url'    => 'str',
			'og_type'          => 'str',
			'image_action'     => 'str',
			'display_image'    => 'bool',
		]);

		// Hiç input yoksa atla (add formunda alanlar boş bırakılmış olabilir)
		$hasAnyInput = false;
		foreach (['meta_title', 'meta_description', 'focus_keyword', 'og_type'] AS $f)
		{
			if (!empty($input[$f]))
			{
				$hasAnyInput = true;
				break;
			}
		}
		$action = $input['image_action'] ?? '';
		$hasUpload = ($action === 'custom' && $this->request->getFile('selamt_xfrmseo_featured_image', false));

		if (!$hasAnyInput && !$hasUpload && !isset($input['display_image']))
		{
			return;
		}

		/** @var Saver $saver */
		$saver = $this->service('SelamT\XFRMSeoBoost:Meta\Saver', $resource);
		$saver->setFromInput($input);
		$saver->save();

		// Image upload (varsa)
		if ($action === 'custom' && $hasUpload)
		{
			$fresh = $this->em()->find(
				'XFRM:ResourceItem',
				$resource->resource_id,
				['SeoMeta', 'SeoMeta.FeaturedImage']
			);
			if (!$fresh)
			{
				return;
			}

			$upload = $this->request->getFile('selamt_xfrmseo_featured_image', false);
			if ($upload && $upload->isValid())
			{
				/** @var Uploader $uploader */
				$uploader = $this->service('SelamT\XFRMSeoBoost:Image\Uploader', $fresh);
				$uploader->setUpload($upload);
				$uploader->save();
			}
		}
	}
}
