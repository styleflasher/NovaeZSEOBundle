<?php

declare(strict_types=1);

/**
 * NovaeZSEOBundle SeoMetadataFieldTypeRegistry.
 *
 * @package   Novactive\Bundle\eZSEOBundle
 *
 * @author    Novactive <novaseobundle@novactive.com>
 * @copyright 2021 Novactive
 * @license   https://github.com/Novactive/NovaeZSEOBundle/blob/master/LICENSE MIT Licence
 */
namespace Novactive\Bundle\eZSEOBundle\Core\FieldType\MetaFieldConverter;

use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Symfony\Component\Form\FormBuilderInterface;

class SeoMetadataFieldTypeRegistry
{
    /** @var SeoMetadataFieldTypeInterface[] */
    protected $metaFieldTypes;

    /** @var ConfigResolverInterface */
    protected $configResolver;

    /**
     * SeoMetadataFieldTypeRegistry constructor.
     *
     * @param SeoMetadataFieldTypeInterface[] $metaFieldTypes
     */
    public function __construct(iterable $metaFieldTypes)
    {
        foreach ($metaFieldTypes as $metumFieldType) {
            $this->addMetaFieldType($metumFieldType);
        }
    }

    #[\Symfony\Contracts\Service\Attribute\Required]
    public function setConfigResolver(ConfigResolverInterface $configResolver): void
    {
        $this->configResolver = $configResolver;
    }

    public function addMetaFieldType(SeoMetadataFieldTypeInterface $seoMetadataFieldType): void
    {
        $this->metaFieldTypes[] = $seoMetadataFieldType;
    }

    public function fromHash($hash): array
    {
        $metasConfig = $this->configResolver->getParameter('fieldtype_metas', 'nova_ezseo');

        $metas = [];
        foreach ($hash as $hashItem) {
            if (!is_array($hashItem)) {
                continue;
            }

            $fieldConfig = $metasConfig[$hashItem['meta_name']] ?? null;
            $fieldType = $fieldConfig['type'] ?? SeoMetadataDefaultFieldType::IDENTIFIER;
            foreach ($this->metaFieldTypes as $metumFieldType) {
                if (!$metumFieldType->support($fieldType)) {
                    continue;
                }

                $metas[] = $metumFieldType->fromHash($hashItem);
            }
        }

        return $metas;
    }

    public function mapForm(FormBuilderInterface &$formBuilder, array $params, string $fieldType): void
    {
        foreach ($this->metaFieldTypes as $metumFieldType) {
            if (!$metumFieldType->support($fieldType)) {
                continue;
            }

            $metumFieldType->mapForm($formBuilder, $params);
        }
    }
}
