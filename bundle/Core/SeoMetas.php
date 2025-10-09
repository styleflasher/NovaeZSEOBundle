<?php

declare(strict_types=1);

/**
 * NovaeZSEOBundle Metas list provider for Admin UI.
 *
 * @package   Novactive\Bundle\eZSEOBundle
 *
 * @author    Novactive <novaseobundle@novactive.com>
 * @copyright 2015 Novactive
 * @license   https://github.com/Novactive/NovaeZSEOBundle/blob/master/LICENSE MIT Licence
 */
namespace Novactive\Bundle\eZSEOBundle\Core;

use Ibexa\Contracts\AdminUi\UI\Config\ProviderInterface;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;

class SeoMetas implements ProviderInterface
{
    public function __construct(protected \Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface $configResolver)
    {
    }

    /**
     * @return list<mixed> Anything that is serializable via json_encode()
     */
    public function getConfig(): array
    {
        $list = [];
        $metas = $this->configResolver->getParameter('fieldtype_metas', 'nova_ezseo');
        foreach ($metas as $metaIdentifier => $meta) {
            $meta['identifier'] = $metaIdentifier;
            $list[] = $meta;
        }

        return $list;
    }
}
