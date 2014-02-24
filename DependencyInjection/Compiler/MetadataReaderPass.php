<?php

namespace Asoc\DadatataBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class MetadataReaderPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $descriptions = $container->findTaggedServiceIds('asoc_dadatata.metadata_reader');
        $factory = $container->getDefinition('asoc_dadatata.metadata.examiner');

        $collection = [];
        foreach ($descriptions as $serviceId => $_) {
            $collection[] = $container->getDefinition($serviceId);
        }

        $factory->replaceArgument(1, $collection);
    }
}
