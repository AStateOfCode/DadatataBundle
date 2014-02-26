<?php

namespace Asoc\DadatataBundle\DependencyInjection\Compiler;

use Asoc\Dadatata\Exception\InvalidFilterOptionsException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

/**
 * Used to pre-validate the filter options.
 *
 * Class FilterPass
 * @package Asoc\DadatataBundle\DependencyInjection\Compiler
 */
class ConfiguredFilterPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $filterServices = $container->findTaggedServiceIds('asoc_dadatata.configured_filter');

        if(count($filterServices) === 0) {
            return;
        }

        foreach ($filterServices as $id => $tagAttributes) {
            $filterDefinition = $container->getDefinition($id);
            $filterType = $tagAttributes[0]['type'];
            $filterName = $tagAttributes[0]['alias'];

            // the options parent definition
            $optionsParentParamId = sprintf('asoc_dadatata.filter.aliased.%s.options_parent', $filterType);
            $optionsParentDefinitionId = $container->getParameter($optionsParentParamId);

            // set by the extension, containts the options array from the config
            $filterOptionsParamId = sprintf('%s.options', $id);
            $filterOptions = $container->getParameter($filterOptionsParamId);

            // build the options object definition
            $optionsDefinitionId = $filterOptionsParamId;
            $optionsDefinition = new DefinitionDecorator($optionsParentDefinitionId);
            $optionsDefinition->setPublic(false);
            $optionsDefinition->addArgument($filterOptions);

            // to create the object here, for some reason, we need to set the class of the options definition
            // therefore, we get the parent options definition and read the class directly
            $optionsParentDefinition = $container->getDefinition($optionsParentDefinitionId);
            $optionsDefinition->setClass($optionsParentDefinition->getClass());

            // clear the tags from this service as they are only needed during the container build
            $filterDefinition->clearTags();

            // add the options object definition to the container
            $container->setDefinition($optionsDefinitionId, $optionsDefinition);

            // tell the container to inject the options object
            $filterDefinition->addMethodCall('setOptions', [new Reference($optionsDefinitionId)]);

            // validate the options by creating the options object
            try {
                $container->get($optionsDefinitionId);
            }
            catch(InvalidOptionsException $e) {
                $message = sprintf('Filter options not valid for: %s (%s)', $filterName, $filterType);
                throw new InvalidFilterOptionsException($message, 0, $e);
            }
        }
    }
}
