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

use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;

class DummyCustomFallback implements CustomFallbackInterface
{
    public function getMetaContent($metaName, ContentInfo $contentInfo): string
    {
        return sprintf('This is meta %s and Content Id: %d', $metaName, $contentInfo->id);
    }
}
