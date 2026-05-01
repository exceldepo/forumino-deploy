<?php

namespace SelamT\XFRMSeoBoost\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $resource_category_id
 * @property string $default_og_type
 *
 * RELATIONS
 * @property-read \XFRM\Entity\Category|null $Category
 */
class CategorySettings extends Entity
{
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_st_xfrmseo_category';
		$structure->shortName = 'SelamT\XFRMSeoBoost:CategorySettings';
		$structure->primaryKey = 'resource_category_id';

		$structure->columns = [
			'resource_category_id' => ['type' => self::UINT, 'required' => true],
			'default_og_type'      => ['type' => self::STR, 'maxLength' => 50, 'default' => '',
				'allowedValues' => ['', 'website', 'article', 'product', 'video.other', 'music.song'],
			],
		];

		$structure->relations = [
			'Category' => [
				'entity' => 'XFRM:Category',
				'type' => self::TO_ONE,
				'conditions' => 'resource_category_id',
				'primary' => true,
			],
		];

		$structure->getters = [];
		$structure->options = [];

		return $structure;
	}
}
