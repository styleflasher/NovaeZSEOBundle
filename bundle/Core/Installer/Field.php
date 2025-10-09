<?php

declare(strict_types=1);

namespace Novactive\Bundle\eZSEOBundle\Core\Installer;

use DateTime;
use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;

class Field
{
    private ?string $errorMessage = null;

    /**
     * Constructor.
     */
    public function __construct(private readonly ContentTypeService $contentTypeService, private readonly ConfigResolverInterface $configResolver)
    {
    }

    public function addToContentType(string $fieldName, ContentType $contentType): bool
    {
        try {
            $contentTypeDraft = $this->contentTypeService->loadContentTypeDraft($contentType->id);
        } catch (NotFoundException) {
            $contentTypeDraft = $this->contentTypeService->createContentTypeDraft($contentType);
        }

        $contentTypeUpdateStruct = $this->contentTypeService->newContentTypeUpdateStruct();
        $contentTypeUpdateStruct->modificationDate = new DateTime();

        $knowLanguage = array_keys($contentType->getDescriptions());

        if (!\in_array($contentType->mainLanguageCode, $knowLanguage)) {
            $knowLanguage[] = $contentType->mainLanguageCode;
        }

        $fieldDefinitionCreateStruct = $this->contentTypeService->newFieldDefinitionCreateStruct(
            $fieldName,
            'novaseometas'
        );

        $fieldDefinitionCreateStruct->names =
            array_fill_keys(
                $knowLanguage,
                $this->configResolver->getParameter('meta_field_name', 'novactive.novaseobundle')
            );
        $fieldDefinitionCreateStruct->descriptions =
            array_fill_keys(
                $knowLanguage,
                $this->configResolver->getParameter('meta_field_description', 'novactive.novaseobundle')
            );
        $fieldDefinitionCreateStruct->fieldGroup =
            $this->configResolver->getParameter('meta_field_group', 'novactive.novaseobundle');
        $fieldDefinitionCreateStruct->position = 100;
        $fieldDefinitionCreateStruct->isTranslatable = true;
        $fieldDefinitionCreateStruct->isRequired = false;
        $fieldDefinitionCreateStruct->isSearchable = false;
        $fieldDefinitionCreateStruct->isInfoCollector = false;

        try {
            $this->contentTypeService->updateContentTypeDraft($contentTypeDraft, $contentTypeUpdateStruct);

            if (null == $contentTypeDraft->getFieldDefinition($fieldName)) {
                $this->contentTypeService->addFieldDefinition($contentTypeDraft, $fieldDefinitionCreateStruct);
            }

            $this->contentTypeService->publishContentTypeDraft($contentTypeDraft);

            return true;
        } catch (\Exception $exception) {
            $this->errorMessage = $exception->getMessage();

            return false;
        }
    }

    public function fieldExists(string $fieldName, ContentType $contentType): bool
    {
        $fieldDefinition = $contentType->getFieldDefinition($fieldName);

        return $fieldDefinition instanceof \Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }
}
