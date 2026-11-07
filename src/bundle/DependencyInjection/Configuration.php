<?php

declare(strict_types=1);

namespace Masilia\Bundle\EditorialToolbox\DependencyInjection;

use Ibexa\Bundle\Core\DependencyInjection\Configuration\SiteAccessAware;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class Configuration extends SiteAccessAware\Configuration
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('masilia_editorial_toolbox');
        $rootNode = $treeBuilder->getRootNode();

        $systemNode = $this->generateScopeBaseNode($rootNode);
        $systemNode
            ->scalarNode('blocks_field')
                ->info('Identifier of the RelationList field that holds the ordered page blocks.')
            ->end()
            ->scalarNode('admin_siteaccess')
                ->info('Siteaccess used to build back-office (admin) URLs from the front-end site.')
            ->end();

        return $treeBuilder;
    }
}
