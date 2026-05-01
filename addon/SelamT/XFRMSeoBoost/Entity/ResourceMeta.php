<?php

namespace SelamT\XFRMSeoBoost\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $resource_id
 * @property string $meta_title
 * @property string $meta_description
 * @property string $focus_keyword
 * @property string $canonical_url
 * @property string $og_type
 * @property int $image_id
 * @property bool $display_image
 *
 * RELATIONS
 * @property-read \SelamT\XFRMSeoBoost\Entity\FeaturedImage|null $FeaturedImage
 * @property-read \XFRM\Entity\ResourceItem|null $Resource
 */
class ResourceMeta extends Entity
{
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_st_xfrmseo_meta';
		$structure->shortName = 'SelamT\XFRMSeoBoost:ResourceMeta';
		$structure->primaryKey = 'resource_id';

		$structure->columns = [
			'resource_id'      => ['type' => self::UINT, 'required' => true],
			'meta_title'       => ['type' => self::STR, 'maxLength' => 200, 'default' => ''],
			'meta_description' => ['type' => self::STR, 'maxLength' => 500, 'default' => ''],
			'focus_keyword'    => ['type' => self::STR, 'maxLength' => 100, 'default' => ''],
			'canonical_url'    => ['type' => self::STR, 'maxLength' => 500, 'default' => ''],
			'og_type'          => ['type' => self::STR, 'maxLength' => 50, 'default' => '',
				'allowedValues' => ['', 'website', 'article', 'product', 'video.other', 'music.song'],
			],
			'image_id'         => ['type' => self::UINT, 'default' => 0],
			'display_image'    => ['type' => self::BOOL, 'default' => true],
		];

		$structure->relations = [
			'FeaturedImage' => [
				'entity' => 'SelamT\XFRMSeoBoost:FeaturedImage',
				'type' => self::TO_ONE,
				'conditions' => [['image_id', '=', '$image_id']],
				'primary' => true,
			],
			'Resource' => [
				'entity' => 'XFRM:ResourceItem',
				'type' => self::TO_ONE,
				'conditions' => 'resource_id',
				'primary' => true,
			],
		];

		$structure->getters = [];
		$structure->options = [];

		return $structure;
	}
}
