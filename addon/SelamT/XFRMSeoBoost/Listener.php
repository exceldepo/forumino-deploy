<?php

namespace SelamT\XFRMSeoBoost;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Manager as EntityManager;
use XF\Mvc\Entity\Structure;

class Listener
{
	/**
	 * code_event_listener: entity_structure
	 *
	 * XFRM:ResourceItem entity'sine SeoMeta ilişkisini eklenir.
	 * XFRM dosyalarına dokunmadan, sadece ilişki tanımı yapılır.
	 */
	public static function entityStructure(EntityManager $em, Structure &$structure): void
	{
		switch ($structure->shortName)
		{
			case 'XFRM:ResourceItem':
				$structure->relations['SeoMeta'] = [
					'entity' => 'SelamT\XFRMSeoBoost:ResourceMeta',
					'type' => Entity::TO_ONE,
					'conditions' => 'resource_id',
					'primary' => true,
				];
				break;

			case 'XFRM:Category':
				$structure->relations['SeoSettings'] = [
					'entity' => 'SelamT\XFRMSeoBoost:CategorySettings',
					'type' => Entity::TO_ONE,
					'conditions' => 'resource_category_id',
					'primary' => true,
				];
				break;
		}
	}
}
