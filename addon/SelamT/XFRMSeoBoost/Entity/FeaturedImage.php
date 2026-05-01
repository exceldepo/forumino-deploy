<?php

namespace SelamT\XFRMSeoBoost\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $image_id
 * @property int $resource_id
 * @property string $file_name
 * @property string $file_hash
 * @property int $file_size
 * @property int $width
 * @property int $height
 * @property int $upload_date
 */
class FeaturedImage extends Entity
{
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_st_xfrmseo_image';
		$structure->shortName = 'SelamT\XFRMSeoBoost:FeaturedImage';
		$structure->primaryKey = 'image_id';

		$structure->columns = [
			'image_id'    => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'resource_id' => ['type' => self::UINT, 'required' => true],
			'file_name'   => ['type' => self::STR, 'maxLength' => 255, 'required' => true],
			'file_hash'   => ['type' => self::STR, 'maxLength' => 32, 'required' => true],
			'file_size'   => ['type' => self::UINT, 'required' => true],
			'width'       => ['type' => self::UINT, 'default' => 0],
			'height'      => ['type' => self::UINT, 'default' => 0],
			'upload_date' => ['type' => self::UINT, 'default' => 0],
		];

		$structure->getters = [
			'extension' => true,
		];

		$structure->options = [];

		return $structure;
	}

	public function getExtension(): string
	{
		$pos = strrpos($this->file_name, '.');
		return $pos === false ? '' : strtolower(substr($this->file_name, $pos + 1));
	}

	/**
	 * data:// public flysystem'da görselin yolu.
	 */
	public function getDataAbstractedPath(): string
	{
		return sprintf(
			'data://st_xfrmseo_images/%d-%s.%s',
			$this->image_id,
			$this->file_hash,
			$this->extension ?: 'jpg'
		);
	}

	public function getDataUrl(): string
	{
		// data:// public flysystem'daki dosyanın tam URL'sini üret.
		// data://st_xfrmseo_images/X-hash.jpg  ->  <boardUrl>/data/st_xfrmseo_images/X-hash.jpg
		$relPath = sprintf(
			'st_xfrmseo_images/%d-%s.%s',
			$this->image_id,
			$this->file_hash,
			$this->extension ?: 'jpg'
		);
		$boardUrl = \XF::options()->boardUrl ?: \XF::app()->request()->getRootUrl();
		return rtrim($boardUrl, '/') . '/data/' . $relPath;
	}
}
