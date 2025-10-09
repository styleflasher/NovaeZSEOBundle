<?php

declare(strict_types=1);

/**
 * NovaeZSEOBundle.
 *
 * @package   Novactive\Bundle\eZSEOBundle
 *
 * @author    Novactive <novaseobundle@novactive.com>
 * @copyright 2015 Novactive
 * @license   https://github.com/Novactive/NovaeZSEOBundle/blob/master/LICENSE MIT Licence
 */
namespace Novactive\Bundle\eZSEOBundle\Core;

use Doctrine\Bundle\DoctrineBundle\Mapping\ContainerEntityListenerResolver;
use Doctrine\Common\Cache\Psr6\DoctrineProvider;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\Persistence\ManagerRegistry as Registry;
use Ibexa\Bundle\Core\ApiLoader\RepositoryConfigurationProvider;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class SiteAccessAwareEntityManagerFactory
{
    public function __construct(private readonly Registry $registry, private readonly \Ibexa\Contracts\Core\Container\ApiLoader\RepositoryConfigurationProviderInterface $repositoryConfigurationProvider, private readonly ContainerEntityListenerResolver $containerEntityListenerResolver, private array $settings)
    {
    }

    private function getConnectionName(): string
    {
        $config = $this->repositoryConfigurationProvider->getRepositoryConfig();

        return $config['storage']['connection'] ?? 'default';
    }

    public function get(): EntityManagerInterface
    {
        $connectionName = $this->getConnectionName();
        // If it is the default connection then we don't bother we can directly use the default entity Manager
        if ('default' === $connectionName) {
            return $this->registry->getManager();
        }

        $connection = $this->registry->getConnection($connectionName);

        /** @var \Doctrine\DBAL\Connection $connection */
        $arrayAdapter = new ArrayAdapter();
        $configuration = new Configuration();
        $configuration->setMetadataCacheImpl(DoctrineProvider::wrap($arrayAdapter));

        $annotationDriver = $configuration->newDefaultAnnotationDriver(__DIR__.'/../Entity', false);
        $configuration->setMetadataDriverImpl($annotationDriver);
        $configuration->setQueryCacheImpl(DoctrineProvider::wrap($arrayAdapter));
        $configuration->setProxyDir($this->settings['cache_dir'].'/eZSEOBundle/');
        $configuration->setProxyNamespace('eZSEOBundle\Proxies');
        $configuration->setAutoGenerateProxyClasses($this->settings['debug']);
        $configuration->setEntityListenerResolver($this->containerEntityListenerResolver);
        $configuration->setNamingStrategy(new UnderscoreNamingStrategy());

        return EntityManager::create($connection, $configuration);
    }
}
