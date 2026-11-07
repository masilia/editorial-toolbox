<?php

declare(strict_types=1);

namespace Masilia\Bundle\EditorialToolbox\DependencyInjection;

use Ibexa\Bundle\Core\DependencyInjection\Configuration\SiteAccessAware\ConfigurationProcessor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class MasiliaEditorialToolboxExtension extends Extension
{
    public const ALIAS = 'masilia_editorial_toolbox';

    public function getAlias(): string
    {
        return self::ALIAS;
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('default_settings.yaml');
        $loader->load('services.yaml');

        $processor = new ConfigurationProcessor($container, self::ALIAS);
        $processor->mapSetting('blocks_field', $config);
        $processor->mapSetting('admin_siteaccess', $config);
    }
}
