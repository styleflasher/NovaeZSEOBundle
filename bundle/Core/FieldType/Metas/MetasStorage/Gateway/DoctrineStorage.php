<?php

declare(strict_types=1);

namespace Novactive\Bundle\eZSEOBundle\Core\FieldType\Metas\MetasStorage\Gateway;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\ParameterType;
use Ibexa\Contracts\Core\Persistence\Content\Field;
use Ibexa\Contracts\Core\Persistence\Content\VersionInfo;
use Novactive\Bundle\eZSEOBundle\Core\FieldType\Metas\MetasStorage\Gateway;

class DoctrineStorage extends Gateway
{
    public const TABLE = 'novaseo_meta';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function storeFieldData(VersionInfo $versionInfo, Field $field): void
    {
        foreach ($field->value->externalData as $meta) {
            $insertQuery = $this->connection->createQueryBuilder();
            $insertQuery
                ->insert($this->connection->quoteIdentifier(self::TABLE))
                ->values(
                    [
                        $this->connection->quoteIdentifier('meta_name') => ':meta_name',
                        $this->connection->quoteIdentifier('meta_content') => ':meta_content',
                        $this->connection->quoteIdentifier('objectattribute_id') => ':objectattribute_id',
                        $this->connection->quoteIdentifier('objectattribute_version') => ':objectattribute_version',
                    ]
                )
                ->setParameter('meta_name', $meta['meta_name'], ParameterType::STRING)
                ->setParameter('meta_content', $meta['meta_content'], ParameterType::STRING)
                ->setParameter('objectattribute_id', $field->id, ParameterType::INTEGER)
                ->setParameter('objectattribute_version', $versionInfo->versionNo, ParameterType::INTEGER);

            $insertQuery->execute();
        }
    }

    public function getFieldData(VersionInfo $versionInfo, Field $field): void
    {
        $field->value->externalData = $this->loadFieldData($versionInfo, $field);
    }

    public function deleteFieldData(VersionInfo $versionInfo, array $fieldIds): void
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->delete($this->connection->quoteIdentifier(self::TABLE))
            ->where(
                $queryBuilder->expr()->andX(
                    $queryBuilder->expr()->in(
                        $this->connection->quoteIdentifier('objectattribute_id'),
                        $fieldIds
                    ),
                    $queryBuilder->expr()->eq(
                        $this->connection->quoteIdentifier('objectattribute_version'),
                        ':version'
                    )
                )
            )
            ->setParameter('version', $versionInfo->versionNo, ParameterType::INTEGER);

        $queryBuilder->execute();
    }

    public function loadFieldData(VersionInfo $versionInfo, Field $field): array
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->distinct()
            ->from($this->connection->quoteIdentifier(self::TABLE))
            ->where(
                $queryBuilder->expr()->andX(
                    $queryBuilder->expr()->eq(
                        $this->connection->quoteIdentifier('objectattribute_id'),
                        ':objectattribute_id'
                    ),
                    $queryBuilder->expr()->eq(
                        $this->connection->quoteIdentifier('objectattribute_version'),
                        ':objectattribute_version'
                    )
                )
            )
            ->setParameter('objectattribute_id', $field->id, ParameterType::INTEGER)
            ->setParameter('objectattribute_version', $versionInfo->versionNo, ParameterType::INTEGER);

        $statement = $queryBuilder->execute();

        return $statement->fetchAll(FetchMode::ASSOCIATIVE);
    }
}
