<?php

declare(strict_types=1);

/**
 * NovaeZSEOBundle Extension.
 *
 * @package   Novactive\Bundle\eZSEOBundle
 *
 * @author    Novactive <novaseobundle@novactive.com>
 * @copyright 2015 Novactive
 * @license   https://github.com/Novactive/NovaeZSEOBundle/blob/master/LICENSE MIT Licence
 */
namespace Novactive\Bundle\eZSEOBundle\DependencyInjection;

use Ibexa\Bundle\Core\DependencyInjection\Configuration\SiteAccessAware\ConfigurationProcessor;
use Ibexa\Bundle\Core\DependencyInjection\Configuration\SiteAccessAware\ContextualizerInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Yaml\Yaml;

class NovaeZSEOExtension extends Extension implements PrependExtensionInterface
{
    #[\Override]
    public function getAlias(): string
    {
        return 'nova_ezseo';
    }

    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('assetic', ['bundles' => ['NovaeZSEOBundle']]);

        $configs = [
            'wildcard_routing.yml' => 'ibexa',
            'ez_field_templates.yml' => 'ibexa',
            'variations.yml' => 'ibexa',
            'ibexa.yaml' => 'ibexa',
            'admin_ui/ez_field_templates.yml' => 'ibexa',
        ];

        foreach ($configs as $fileName => $extensionName) {
            $configFile = __DIR__.'/../Resources/config/'.$fileName;
            $config = Yaml::parse(file_get_contents($configFile));
            $container->prependExtensionConfig($extensionName, $config);
            $container->addResource(new FileResource($configFile));
        }
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $yamlFileLoader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $yamlFileLoader->load('services.yml');
        $yamlFileLoader->load('services_nonautowired.yml');
        $yamlFileLoader->load('default_settings.yml');
        $yamlFileLoader->load('admin_ui/services.yml');

        $configurationProcessor = new ConfigurationProcessor($container, 'nova_ezseo');
        $configurationProcessor->mapSetting('fieldtype_metas_identifier', $config);
        $configurationProcessor->mapSetting('fieldtype_metas', $config);
        $configurationProcessor->mapSetting('google_verification', $config);
        $configurationProcessor->mapSetting('google_gatracker', $config);
        $configurationProcessor->mapSetting('google_anonymizeIp', $config);
        $configurationProcessor->mapSetting('bing_verification', $config);
        $configurationProcessor->mapSetting('limit_to_rootlocation', $config);
        $configurationProcessor->mapSetting('display_images_in_sitemap', $config);
        $configurationProcessor->mapSetting('robots', $config);
        $configurationProcessor->mapConfigArray('fieldtype_metas', $config, ContextualizerInterface::MERGE_FROM_SECOND_LEVEL);
        $configurationProcessor->mapConfigArray('default_metas', $config);
        $configurationProcessor->mapConfigArray('default_links', $config);
        $configurationProcessor->mapConfigArray('sitemap_excludes', $config, ContextualizerInterface::MERGE_FROM_SECOND_LEVEL);
        $configurationProcessor->mapConfigArray('sitemap_includes', $config, ContextualizerInterface::MERGE_FROM_SECOND_LEVEL);
        $configurationProcessor->mapConfigArray('robots_disallow', $config);
        $configurationProcessor->mapConfigArray('robots', $config, ContextualizerInterface::MERGE_FROM_SECOND_LEVEL);

        if ($container->hasParameter('novactive.novaseobundle.admin_user_id')) {
            $container->setParameter(
                'novactive.novaseobundle.default.admin_user_id',
                $container->getParameter('novactive.novaseobundle.admin_user_id')
            );
        }
    }
}
