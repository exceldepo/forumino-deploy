<?php

namespace SelamT\XFRMSeoBoost\XFRM\Pub\Controller;

use SelamT\XFRMSeoBoost\Service\Meta\Saver;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Redirect;
use XF\Mvc\Reply\View;

class ResourceItem extends XFCP_ResourceItem
{
	public function actionEdit(ParameterBag $params)
	{
		$reply = parent::actionEdit($params);

		if ($this->isPost())
		{
			// Üst metod başarıyla redirect döndürdüyse kaydet (validation geçti demek).
			if ($reply instanceof Redirect)
			{
				$resource = $this->em()->find('XFRM:ResourceItem', $params->resource_id, ['SeoMeta']);
				if ($resource)
				{
					$this->seoMetaSaveFromRequest($resource);
				}
			}
		}
		else
		{
			// GET: form'a meta verilerini ek viewParam olarak geçir.
			if ($reply instanceof View)
			{
				$resource = $reply->getParam('resource');
				if ($resource && $resource instanceof \XFRM\Entity\ResourceItem)
				{
					$reply->setParam('seoMeta', $resource->SeoMeta);
				}
			}
		}

		return $reply;
	}

	protected function seoMetaSaveFromRequest(\XFRM\Entity\ResourceItem $resource): void
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

		/** @var Saver $saver */
		$saver = $this->service('SelamT\XFRMSeoBoost:Meta\Saver', $resource);
		$saver->setFromInput($input);
		$saver->save();

		// Meta save'den SONRA cache'i tazele (Uploader $resource->SeoMeta ile çalışıyor).
		$fresh = $this->em()->find(
			'XFRM:ResourceItem',
			$resource->resource_id,
			['SeoMeta', 'SeoMeta.FeaturedImage']
		);
		if (!$fresh)
		{
			return;
		}

		// Image action: 'keep' (default) | 'custom' (yeni yükle) | 'delete' (sil)
		$action = $input['image_action'] ?? 'keep';

		if ($action === 'delete')
		{
			/** @var \SelamT\XFRMSeoBoost\Service\Image\Uploader $uploader */
			$uploader = $this->service('SelamT\XFRMSeoBoost:Image\Uploader', $fresh);
			$uploader->delete();
			return;
		}

		if ($action === 'custom')
		{
			$upload = $this->request->getFile('selamt_xfrmseo_featured_image', false);
			if ($upload && $upload->isValid())
			{
				/** @var \SelamT\XFRMSeoBoost\Service\Image\Uploader $uploader */
				$uploader = $this->service('SelamT\XFRMSeoBoost:Image\Uploader', $fresh);
				$uploader->setUpload($upload);
				$uploader->save();
			}
		}
		// 'keep' veya bilinmeyen value → hiçbir şey yapma
	}
}
