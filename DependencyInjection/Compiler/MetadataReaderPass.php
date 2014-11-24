<?php

namespace Asoc\DadatataBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class MetadataReaderPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $readerServices = $container->findTaggedServiceIds('asoc_dadatata.metadata_reader');

        $aliases = [];
        foreach ($readerServices as $id => $tagAttributes) {
            foreach ($tagAttributes as $attributes) {
                $alias           = sprintf('asoc_dadatata.metadata.reader.aliased.%s', $attributes['alias']);
                $aliases[$alias] = new Alias($id, false);
            }
        }

        $container->addAliases($aliases);
    }
}
