<?php

namespace Asoc\DadatataBundle\DependencyInjection\Compiler;

use Asoc\Dadatata\Exception\InvalidFilterOptionsException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

/**
 * Used to register filter under a specific alias. This is done to support filters outside of the asoc_dadatata space.
 *
 * Class FilterPass
 *
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

                if (isset($attributes['options'])) {
                    $optionsClassParamId = sprintf(
                        'asoc_dadatata.filter.aliased.%s.options_parent',
                        $attributes['alias']
                    );
                    $optionsClass        = $attributes['options'];
                    $container->setParameter($optionsClassParamId, $optionsClass);
                }
            }
        }

        $this->processDirectFilter($container->getParameter('asoc_dadatata.filter.config'), $container);
        $this->processNestedFilter($container->getParameter('asoc_dadatata.filter.config'), $container);
        $this->processFilterOptions($container->getParameter('asoc_dadatata.filter.config'), $container);

        $this->validateFilter($container->getParameter('asoc_dadatata.filter.config'), $container);

        $this->processVariator($container->getParameter('asoc_dadatata.variator.config'), $container);

        $container->setParameter('asoc_dadatata.filter.config', null);
        $container->setParameter('asoc_dadatata.variator.config', null);
    }

    private function validateFilter(array $config, ContainerBuilder $container)
    {
        $toBeRemoved        = [];
        $checkFilterService = function ($currentFilterId, Definition $filterDefinition) use (
            &$container,
            &$checkFilterService,
            &$toBeRemoved
        ) {
            if ($filterDefinition->getClass() === '%asoc_dadatata.filter.chain.class%'
                || $filterDefinition->getClass() === '%asoc_dadatata.filter.aggregate.class%'
            ) {
                foreach ($filterDefinition->getArguments()[0] as $filterReference) {
                    /** @var Reference $filterReference */
                    $nestedFilterId = (string)$filterReference;

                    if (!$container->hasDefinition($nestedFilterId) || isset($toBeRemoved[$nestedFilterId]) === true) {
                        $toBeRemoved[$currentFilterId] = true;
                        break;
                    } else {
                        $nestedFilterDefinition = $container->getDefinition($nestedFilterId);
                        $checkFilterService($nestedFilterId, $nestedFilterDefinition);
                    }
                }
            } else {
                if ($filterDefinition instanceof DefinitionDecorator) {
                    if (!$container->hasDefinition(
                            $filterDefinition->getParent()
                        ) || isset($toBeRemoved[$filterDefinition->getParent()]) === true
                    ) {
                        $toBeRemoved[$currentFilterId] = true;
                    }
                }
            }
        };

        // remove all filter services until no new services are being removed
        $toBeRemovedNum = 0;
        for (; ;) {
            foreach ($config as $filterName => $filterConfig) {
                $filterId = sprintf('asoc_dadatata.%s_filter', $filterName);

                if (!$container->hasDefinition($filterId)) {
                    continue;
                }

                $filterDefinition = $container->getDefinition($filterId);
                $checkFilterService($filterId, $filterDefinition);
            }
            $num = count($toBeRemoved);
            if ($num === $toBeRemovedNum) {
                break;
            }

            $toBeRemovedNum = $num;
        }

        foreach ($toBeRemoved as $serviceId => $_) {
            $container->removeDefinition($serviceId);
        }
    }

    private function processDirectFilter(array $config, ContainerBuilder $container)
    {
        foreach ($config as $filterName => $filterConfig) {
            $filterId   = sprintf('asoc_dadatata.%s_filter', $filterName);
            $filterType = $filterConfig['type'];

            if ('chain' === $filterType || 'aggregate' === $filterType) {
                continue;
            }

            $templateId = sprintf('asoc_dadatata.filter.aliased.%s', $filterType);

            if (!$container->hasDefinition($templateId)) {
                continue;
            }

            $filterDefinition = new DefinitionDecorator($templateId);

            if (isset($filterConfig['options']) && count($filterConfig['options']) > 0) {
                $filterOptionsId = sprintf('%s.options', $filterId);
                $container->setParameter($filterOptionsId, $filterConfig['options']);
            }

            $container->setDefinition($filterId, $filterDefinition);
        }
    }

    private function processNestedFilter(array $config, ContainerBuilder $container)
    {
        foreach ($config as $filterName => $filterConfig) {
            $filterId   = sprintf('asoc_dadatata.%s_filter', $filterName);
            $filterType = $filterConfig['type'];

            if ('chain' !== $filterType && 'aggregate' !== $filterType) {
                continue;
            }

            $templateId = sprintf('asoc_dadatata.filter.aliased.%s', $filterType);

            $filterDefinition = new DefinitionDecorator($templateId);

            if ($filterType === 'chain') {
                $filterDefinition = new Definition('%asoc_dadatata.filter.chain.class%');
            } else {
                if ($filterType === 'aggregate') {
                    $filterDefinition = new Definition('%asoc_dadatata.filter.aggregate.class%');
                }
            }

            $filterReferences = [];
            foreach ($filterConfig['filters'] as $innerFilterName) {
                $innerFilterId      = sprintf('asoc_dadatata.%s_filter', $innerFilterName);
                $filterReferences[] = new Reference($innerFilterId);
            }
            $filterDefinition->setArguments([$filterReferences]);

            $container->setDefinition($filterId, $filterDefinition);
        }
    }

    private function processVariator(array $config, ContainerBuilder $container)
    {
        foreach ($config as $variatorName => $variatorConfig) {
            $variatorId = sprintf('asoc_dadatata.%s_variator', $variatorName);
            $variator   = new Definition('Asoc\Dadatata\SimpleVariator');

            $filters = [];
            foreach ($variatorConfig['variants'] as $variant => $filterName) {
                $filterServiceId = sprintf('asoc_dadatata.%s_filter', $filterName);

                if (!$container->hasDefinition($filterServiceId)) {
                    continue;
                }

                $filters[$variant] = new Reference($filterServiceId);
            }

            $variator->addArgument($filters);
            $container->setDefinition($variatorId, $variator);
        }
    }

    private function processFilterOptions(array $config, ContainerBuilder $container)
    {
        foreach ($config as $filterName => $filterConfig) {
            $filterId   = sprintf('asoc_dadatata.%s_filter', $filterName);
            $filterType = $filterConfig['type'];

            if (!$container->hasDefinition($filterId)) {
                continue;
            }
            if ('chain' === $filterType || 'aggregate' === $filterType) {
                continue;
            }

            $filterDefinition = $container->getDefinition($filterId);

            // check if the filter is actually loaded
            $filterAlias = sprintf('asoc_dadatata.filter.aliased.%s', $filterType);
            if (!$container->hasDefinition($filterAlias)) {
                $message = sprintf('Filter type unavailable: %s (used for %s)', $filterType, $filterName);
                //throw new InvalidFilterOptionsException($message);
                continue;
            }

            // the options parent definition
            $optionsParentParamId = sprintf('asoc_dadatata.filter.aliased.%s.options_parent', $filterType);

            if (!$container->hasParameter($optionsParentParamId)) {
                $message = sprintf('Filter does not have any options: %s (used for %s)', $filterType, $filterName);
                //throw new InvalidFilterOptionsException($message);
                continue;
            }

            $optionsParentDefinitionId = $container->getParameter($optionsParentParamId);

            if (!$container->hasDefinition($optionsParentDefinitionId)) {
                $message = sprintf('Filter not loaded: %s (used for %s)', $filterType, $filterName);
                throw new InvalidFilterOptionsException($message);
                continue;
            }

            // set by the extension, containts the options array from the config
            $filterOptionsParamId = sprintf('%s.options', $filterId);
            $optionsDefinitionId = $filterOptionsParamId;

            // build the options object definition
            $optionsDefinition   = new DefinitionDecorator($optionsParentDefinitionId);
            $optionsDefinition->setPublic(false);

            if ($container->hasParameter($filterOptionsParamId)) {
                $filterOptions = $container->getParameter($filterOptionsParamId);
                $optionsDefinition->addArgument($filterOptions);
            }

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
            } catch (InvalidOptionsException $e) {
                $message = sprintf('Filter options not valid for: %s (used for %s)', $filterType, $filterName);
                //throw new InvalidFilterOptionsException($message, 0, $e);
                continue;
            }
        }
    }
}
