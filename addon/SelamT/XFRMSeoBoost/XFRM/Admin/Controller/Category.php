<?php

namespace SelamT\XFRMSeoBoost\XFRM\Admin\Controller;

use SelamT\XFRMSeoBoost\Entity\CategorySettings;
use XF\Mvc\FormAction;
use XF\Mvc\Reply\View;
use XFRM\Entity\Category as XfrmCategory;

class Category extends XFCP_Category
{
	/**
	 * Edit form'una mevcut SeoSettings'i geçiriyoruz ki template render'ında erişilebilsin.
	 */
	public function categoryAddEdit(XfrmCategory $category)
	{
		$reply = parent::categoryAddEdit($category);

		if ($reply instanceof View)
		{
			$reply->setParam('seoSettings', $category->SeoSettings);
		}

		return $reply;
	}

	/**
	 * Save sırasında parent'tan FormAction al, ek complete callback ile bizim CategorySettings'i kaydet.
	 */
	protected function categorySaveProcess(XfrmCategory $category)
	{
		/** @var FormAction $form */
		$form = parent::categorySaveProcess($category);

		$input = $this->filter('selamt_xfrmseo_category', [
			'default_og_type' => 'str',
		]);

		$form->complete(function () use ($category, $input)
		{
			$allowed = ['', 'website', 'article', 'product', 'video.other', 'music.song'];
			$ogType = in_array($input['default_og_type'], $allowed, true) ? $input['default_og_type'] : '';

			/** @var CategorySettings|null $settings */
			$settings = $this->em()->find(
				'SelamT\XFRMSeoBoost:CategorySettings',
				$category->resource_category_id
			);

			// Boş değer + mevcut kayıt yok → satır yaratma. Boş değer + mevcut var → sil.
			if ($ogType === '')
			{
				if ($settings)
				{
					$settings->delete();
				}
				return;
			}

			if (!$settings)
			{
				/** @var CategorySettings $settings */
				$settings = $this->em()->create('SelamT\XFRMSeoBoost:CategorySettings');
				$settings->resource_category_id = $category->resource_category_id;
			}
			$settings->default_og_type = $ogType;
			$settings->save();
		});

		return $form;
	}
}
