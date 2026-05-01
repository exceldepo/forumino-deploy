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
	 * XFRM:ResourceItem entity'sine SeoMeta (1-to-1) ilişkisini ekler.
	 * XFRM dosyalarına dokunmadan, sadece relation tanımı yapılır.
	 */
	public static function entityStructure(EntityManager $em, Structure &$structure): void
	{
		if ($structure->shortName === 'XFRM:ResourceItem')
		{
			$structure->relations['SeoMeta'] = [
				'entity' => 'SelamT\XFRMSeoBoost:ResourceMeta',
				'type' => Entity::TO_ONE,
				'conditions' => 'resource_id',
				'primary' => true,
			];
		}
	}
}
