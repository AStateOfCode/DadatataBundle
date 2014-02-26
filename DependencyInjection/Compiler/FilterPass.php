<?php

namespace Asoc\DadatataBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Used to register filter under a specific alias. This is done to support filters outside of the asoc_dadatata space.
 *
 * Class FilterPass
 * @package Asoc\DadatataBundle\DependencyInjection\Compiler
 */
class FilterPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $filterServices = $container->findTaggedServiceIds('asoc_dadatata.filter');

        foreach ($filterServices as $id => $tagAttributes) {
            foreach ($tagAttributes as $attributes) {
                // actually we would create aliases here, but they somehow don't work with DefinitionDecorators as parent
                // therefore, we actually reference a copy of the definition under another name
                $alias = sprintf('asoc_dadatata.filter.aliased.%s', $attributes['alias']);
                $container->setDefinition($alias, $container->getDefinition($id));

                if(isset($attributes['options'])) {
                    $optionsClassParamId = sprintf('asoc_dadatata.filter.aliased.%s.options_parent', $attributes['alias']);
                    $optionsClass = $attributes['options'];
                    $container->setParameter($optionsClassParamId, $optionsClass);
                }
            }
        }
    }
}
