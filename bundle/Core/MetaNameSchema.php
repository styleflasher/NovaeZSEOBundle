<?php

declare(strict_types=1);

/**
 * NovaeZSEOBundle MetaNameSchema.
 *
 * @package   Novactive\Bundle\eZSEOBundle
 *
 * @author    Novactive <novaseobundle@novactive.com>
 * @copyright 2015 Novactive
 * @license   https://github.com/Novactive/NovaeZSEOBundle/blob/master/LICENSE MIT Licence
 */
namespace Novactive\Bundle\eZSEOBundle\Core;

use Ibexa\Contracts\Core\Persistence\Content\Language\Handler as ContentLanguageHandler;
use Ibexa\Contracts\Core\Persistence\Content\Type as SPIContentType;
use Ibexa\Contracts\Core\Persistence\Content\Type\Handler as ContentTypeHandler;
use Ibexa\Contracts\Core\Repository\Repository as RepositoryInterface;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Ibexa\Contracts\Core\Variation\VariationHandler;
use Ibexa\Contracts\FieldTypeRichText\RichText\Converter as RichTextConverterInterface;
use Ibexa\Core\Base\Exceptions\InvalidArgumentType;
use Ibexa\Core\Base\Exceptions\NotFoundException;
use Ibexa\Core\FieldType\FieldTypeRegistry;
use Ibexa\Core\FieldType\Image\Value as ImageValue;
use Ibexa\Core\FieldType\ImageAsset\Value as ImageAssetValue;
use Ibexa\Core\FieldType\Relation\Value as RelationValue;
use Ibexa\Core\FieldType\RelationList\Type as RelationListType;
use Ibexa\Core\FieldType\RelationList\Value as RelationListValue;
use Ibexa\Core\Helper\TranslationHelper;
use Ibexa\Core\MVC\Exception\SourceImageNotFoundException;
use Ibexa\Core\Repository\NameSchema\NameSchemaService;
use Ibexa\Contracts\Core\Repository\NameSchema\SchemaIdentifierExtractorInterface;
use Ibexa\Core\Repository\Mapper\ContentTypeDomainMapper;
use Ibexa\Core\Repository\Values\Content\VersionInfo;
use Ibexa\FieldTypeRichText\FieldType\RichText\Value as RichTextValue;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class MetaNameSchema extends NameSchemaService
{
    protected ContentTypeDomainMapper $contentTypeDomainMapper;

    /**
     * @var RichTextConverterInterface
     */
    protected $richTextConverter;

    /**
     * @var VariationHandler
     */
    protected $imageVariationService;

    /**
     * @var int
     */
    protected $fieldContentMaxLength = 255;

    /**
     * @var RelationListType
     */
    private readonly \Ibexa\Contracts\Core\FieldType\FieldType $fieldType;

    public function __construct(
        FieldTypeRegistry $fieldTypeRegistry,
        SchemaIdentifierExtractorInterface $schemaIdentifierExtractor,
        EventDispatcherInterface $eventDispatcher,
        ContentTypeHandler $contentTypeHandler,
        ContentLanguageHandler $languageHandler,
        protected \Ibexa\Contracts\Core\Repository\Repository $repository,
        protected \Ibexa\Core\Helper\TranslationHelper $translationHelper,
        private readonly ConfigResolverInterface $configResolver,
        array $settings = []
    ) {
        $settings['limit'] = $this->fieldContentMaxLength;
        $this->contentTypeDomainMapper = new ContentTypeDomainMapper(
            $contentTypeHandler,
            $languageHandler,
            $fieldTypeRegistry
        );

        parent::__construct($fieldTypeRegistry, $schemaIdentifierExtractor, $eventDispatcher, $settings);
        $this->fieldType = $this->fieldTypeRegistry->getFieldType('ezobjectrelationlist');
    }

    public function setRichTextConverter(RichTextConverterInterface $richTextConverter): void
    {
        $this->richTextConverter = $richTextConverter;
    }

    public function setImageVariationService(VariationHandler $variationHandler): void
    {
        $this->imageVariationService = $variationHandler;
    }

    // @param ContentType|null $contentType: @deprecated argument.
    public function resolveMeta(Meta $meta, Content $content, ?ContentType $contentType = null): bool
    {
        $languages = $this->configResolver->getParameter('languages');

        $resolveMultilingue = $this->resolveNameSchema(
            $meta->getContent(),
            $content->getContentType(),
            $content->fields,
            $content->versionInfo->languageCodes
        );
        // we don't fallback on the other languages... it would be very bad for SEO to mix the languages
        if (
            \array_key_exists($languages[0], $resolveMultilingue)
            && ('' !== $resolveMultilingue[$languages[0]])
        ) {
            $meta->setContent($resolveMultilingue[$languages[0]]);

            return true;
        }

        $meta->setContent('');

        return false;
    }

    /**
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function getFieldTitles(
        array $schemaIdentifiers,
        $contentType,
        array $fieldMap,
        $languageCode
    ): array {
        $fieldTitles = [];

        foreach ($schemaIdentifiers as $schemaIdentifier) {
            if (isset($fieldMap[$schemaIdentifier][$languageCode])) {
                if ($contentType instanceof SPIContentType) {
                    $fieldDefinition = null;
                    foreach ($contentType->fieldDefinitions as $spiFieldDefinition) {
                        if ($spiFieldDefinition->identifier === $schemaIdentifier) {
                            $fieldDefinition = $this->contentTypeDomainMapper->buildFieldDefinitionDomainObject(
                                $spiFieldDefinition,
                                $languageCode
                            );
                            break;
                        }
                    }

                    if (null === $fieldDefinition) {
                        $fieldTitles[$schemaIdentifier] = '';
                        continue;
                    }
                } elseif ($contentType instanceof ContentType) {
                    $fieldDefinition = $contentType->getFieldDefinition($schemaIdentifier);
                } else {
                    throw new InvalidArgumentType('$contentType', 'API or SPI variant of ContentType');
                }

                // eZ XML Text
                if ($fieldMap[$schemaIdentifier][$languageCode] instanceof RichTextValue) {
                    $fieldTitles[$schemaIdentifier] = $this->handleRichTextValue(
                        $fieldMap[$schemaIdentifier][$languageCode]
                    );
                    continue;
                }

                // eZ Object Relation
                if ($fieldMap[$schemaIdentifier][$languageCode] instanceof RelationValue) {
                    $fieldTitles[$schemaIdentifier] = $this->handleRelationValue(
                        $fieldMap[$schemaIdentifier][$languageCode],
                        $languageCode
                    );
                    continue;
                }

                // eZ Object Relation List
                if ($fieldMap[$schemaIdentifier][$languageCode] instanceof RelationListValue) {
                    $fieldTitles[$schemaIdentifier] = $this->handleRelationListValue(
                        $fieldMap[$schemaIdentifier][$languageCode],
                        $fieldDefinition,
                        $languageCode
                    );
                    continue;
                }

                // eZ Image
                if ($fieldMap[$schemaIdentifier][$languageCode] instanceof ImageValue) {
                    $fieldTitles[$schemaIdentifier] = $this->handleImageValue(
                        $fieldMap[$schemaIdentifier][$languageCode],
                        $schemaIdentifier,
                        $languageCode
                    );
                    continue;
                }

                // eZ Image asset
                if ($fieldMap[$schemaIdentifier][$languageCode] instanceof ImageAssetValue) {
                    $fieldTitles[$schemaIdentifier] = $this->handleImageAssetValue(
                        $fieldMap[$schemaIdentifier][$languageCode],
                        $schemaIdentifier,
                        $languageCode
                    );
                    continue;
                }

                $fieldType = $this->fieldTypeRegistry->getFieldType($fieldDefinition->fieldTypeIdentifier);

                $fieldTitles[$schemaIdentifier] = $fieldType->getName(
                    $fieldMap[$schemaIdentifier][$languageCode],
                    $fieldDefinition,
                    $languageCode
                );
            }
        }

        return $fieldTitles;
    }

    protected function getVariation(
        ImageValue $imageValue,
        string $identifier,
        string $languageCode,
        string $variationName
    ): string {
        $field = new Field(
            [
                'value' => $imageValue,
                'fieldDefIdentifier' => $identifier,
                'languageCode' => $languageCode,
            ]
        );

        $variation = $this->imageVariationService->getVariation($field, new VersionInfo(), $variationName);

        return $variation->uri;
    }

    /**
     * Get a Text from a Rich text field type.
     */
    protected function handleRichTextValue(RichTextValue $richTextValue): string
    {
        return trim(strip_tags($this->richTextConverter->convert($richTextValue->xml)->saveHTML()));
    }

    /**
     * Get the Relation in text or URL.
     */
    protected function handleRelationValue(RelationValue $relationValue, string $languageCode): string
    {
        if (!$relationValue->destinationContentId) {
            return '';
        }

        $relatedContent = $this->repository->getContentService()->loadContent($relationValue->destinationContentId);
        // @todo: we can probably be better here and handle more than just "image"
        $fieldImageValue = $relatedContent->getFieldValue('image');
        if ($fieldImageValue instanceof \Ibexa\Contracts\Core\FieldType\Value && $fieldImageValue->uri) {
            return $this->getVariation(
                $fieldImageValue,
                'image',
                $languageCode,
                'social_network_image'
            );
        }

        return $this->translationHelper->getTranslatedContentName($relatedContent, $languageCode);
    }

    protected function handleRelationListValue(RelationListValue $relationListValue, \Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition $fieldDefinition, string $languageCode): string
    {
        return $this->fieldType->getName($relationListValue, $fieldDefinition, $languageCode);
    }

    /**
     * Handle a Image attribute.
     */
    protected function handleImageValue(ImageValue $imageValue, string $fieldDefinitionIdentifier, string $languageCode): string
    {
        if (!$imageValue->uri) {
            return '';
        }

        try {
            return $this->getVariation(
                $imageValue,
                $fieldDefinitionIdentifier,
                $languageCode,
                'social_network_image'
            );
        } catch (SourceImageNotFoundException) {
            return '';
        }
    }

    /**
     * Handle a Image Asset attribute.
     */
    protected function handleImageAssetValue(ImageAssetValue $imageAssetValue, string $fieldDefinitionIdentifier, string $languageCode): string
    {
        if (!$imageAssetValue->destinationContentId) {
            return '';
        }

        try {
            $content = $this->repository->getContentService()->loadContent($imageAssetValue->destinationContentId);
        } catch (NotFoundException) {
            return '';
        }

        foreach ($content->getFields() as $field) {
            if ($field->value instanceof ImageValue) {
                return $this->handleImageValue($field->value, $fieldDefinitionIdentifier, $languageCode);
            }
        }

        return '';
    }

    /**
     * Override native function as this prevent usage of `()` inside metas in Ibexa 4.6
     * {@inheritDoc}
     */
    #[\Override]
    protected function filterNameSchema(string $nameSchema): array
    {
        $groupLookupTable = [];

        return [$nameSchema, $groupLookupTable];
    }
}
