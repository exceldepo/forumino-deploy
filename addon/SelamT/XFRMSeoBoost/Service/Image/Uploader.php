<?php

namespace SelamT\XFRMSeoBoost\Service\Image;

use SelamT\XFRMSeoBoost\Entity\FeaturedImage;
use SelamT\XFRMSeoBoost\Entity\ResourceMeta;
use XF\Http\Upload;
use XF\Service\AbstractService;
use XFRM\Entity\ResourceItem;

class Uploader extends AbstractService
{
	public const MAX_SIZE_BYTES = 5 * 1024 * 1024; // 5 MB
	public const ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'webp'];

	protected ResourceItem $resource;
	protected ?Upload $upload = null;
	protected array $errors = [];

	public function __construct(\XF\App $app, ResourceItem $resource)
	{
		parent::__construct($app);
		$this->resource = $resource;
	}

	public function setUpload(Upload $upload): self
	{
		$this->upload = $upload;
		return $this;
	}

	public function validate(): bool
	{
		$this->errors = [];

		if (!$this->upload || !$this->upload->isValid())
		{
			return false;
		}

		if ($this->upload->getFileSize() > self::MAX_SIZE_BYTES)
		{
			$this->errors[] = \XF::phrase('selamt_xfrmseo_error_image_too_large', [
				'max_size' => '5 MB',
			]);
			return false;
		}

		$ext = strtolower(pathinfo($this->upload->getFileName(), PATHINFO_EXTENSION));
		if (!in_array($ext, self::ALLOWED_EXT, true))
		{
			$this->errors[] = \XF::phrase('selamt_xfrmseo_error_image_invalid_type');
			return false;
		}

		return true;
	}

	public function save(): ?FeaturedImage
	{
		if (!$this->validate())
		{
			return null;
		}

		// Önceki görseli sil (varsa)
		$this->deleteCurrent();

		$tempFile = $this->upload->getTempFile();
		$hash = hash_file('md5', $tempFile);
		$size = (int) filesize($tempFile);

		$dimensions = @getimagesize($tempFile) ?: [0, 0];
		$width = (int) ($dimensions[0] ?? 0);
		$height = (int) ($dimensions[1] ?? 0);

		// Önce entity oluştur (image_id almak için)
		/** @var FeaturedImage $image */
		$image = $this->em()->create('SelamT\XFRMSeoBoost:FeaturedImage');
		$image->bulkSet([
			'resource_id' => $this->resource->resource_id,
			'file_name'   => $this->upload->getFileName(),
			'file_hash'   => $hash,
			'file_size'   => $size,
			'width'       => $width,
			'height'      => $height,
			'upload_date' => \XF::$time,
		]);
		$image->save();

		// Dosyayı flysystem üzerinden data:// public alanına yaz.
		// Orphan disk dosyası (DB kaydı silinmiş ama disk'te kalan, veya aynı hash'li
		// dosya tekrar yüklenmesi) varsa önce sil ki "FileExistsException" alma.
		$abstractedPath = $image->getDataAbstractedPath();
		$stream = fopen($tempFile, 'rb');
		try
		{
			$fs = $this->app->fs();

			if ($fs->has($abstractedPath))
			{
				$fs->delete($abstractedPath);
			}

			$fs->writeStream($abstractedPath, $stream);
		}
		finally
		{
			if (is_resource($stream))
			{
				fclose($stream);
			}
		}

		// ResourceMeta'da image_id güncelle
		$meta = $this->getOrCreateMeta();
		$meta->image_id = $image->image_id;
		$meta->save();

		return $image;
	}

	public function delete(): void
	{
		$meta = $this->resource->SeoMeta;
		if (!$meta || !$meta->image_id)
		{
			return;
		}

		$existing = $meta->FeaturedImage;
		if ($existing instanceof FeaturedImage)
		{
			$this->deleteFile($existing);
			$existing->delete();
		}

		$meta->image_id = 0;
		$meta->save();
	}

	protected function deleteCurrent(): void
	{
		$meta = $this->resource->SeoMeta;
		if (!$meta || !$meta->image_id)
		{
			return;
		}
		$existing = $meta->FeaturedImage;
		if ($existing instanceof FeaturedImage)
		{
			$this->deleteFile($existing);
			$existing->delete();
		}
	}

	protected function deleteFile(FeaturedImage $image): void
	{
		$path = $image->getDataAbstractedPath();
		$fs = $this->app->fs();
		try
		{
			if ($fs->fileExists($path))
			{
				$fs->delete($path);
			}
		}
		catch (\Throwable $e)
		{
			// Sessizce yut: dosya zaten yoksa veya FS hatası varsa entity silmeye devam.
		}
	}

	protected function getOrCreateMeta(): ResourceMeta
	{
		// Önce relation cache'inden dene
		$meta = $this->resource->SeoMeta;

		// Cache stale olabilir (Saver yeni oluşturduktan hemen sonra) — DB'den explicit fetch
		if (!($meta instanceof ResourceMeta))
		{
			/** @var ResourceMeta|null $meta */
			$meta = $this->em()->find('SelamT\XFRMSeoBoost:ResourceMeta', $this->resource->resource_id);
		}

		if ($meta instanceof ResourceMeta)
		{
			return $meta;
		}

		// Gerçekten yok → yeni create
		/** @var ResourceMeta $meta */
		$meta = $this->em()->create('SelamT\XFRMSeoBoost:ResourceMeta');
		$meta->resource_id = $this->resource->resource_id;
		return $meta;
	}

	public function getErrors(): array
	{
		return $this->errors;
	}
}
