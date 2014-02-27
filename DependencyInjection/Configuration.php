<?php

namespace Asoc\DadatataBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Process\ExecutableFinder;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('asoc_dadatata');

        $this->addToolsSection($rootNode);

        return $treeBuilder;
    }

    private function addToolsSection(ArrayNodeDefinition $node) {
        $finder = new ExecutableFinder();
        $convertBin = $finder->find('convert', '/usr/bin/convert');
        $graphicsMagickBin = $finder->find('gm', '/usr/bin/gm');
        $mediainfoBin = $finder->find('mediainfo', '/usr/bin/mediainfo');
        $ffmpegBin = $finder->find('ffmpeg', '/usr/bin/ffmpeg');
        $unoconv = $finder->find('unoconv', '/usr/bin/unoconv');
        $exiftoolBin = $finder->find('exiftool', '/usr/bin/exiftool');
        $pdfBoxBin = $finder->find('pdfbox', '/usr/bin/pdfbox');
        $tesseractBin = $finder->find('tesseract', '/usr/bin/tesseract');
        $jpegoptimBin = $finder->find('jpegoptim', '/usr/bin/jpegoptim');

        $node
            ->children()
                ->arrayNode('examiner')
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->children()
                            ->arrayNode('type_guesser')
                                ->prototype('scalar')->end()
                            ->end()
                            ->arrayNode('reader')
                                ->prototype('scalar')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('filter')
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->beforeNormalization()
                            ->ifString()
                            ->then(function($v) { return ['type' => $v]; })
                        ->end()
                        ->children()
                            ->scalarNode('type')->isRequired()->end()
                            ->arrayNode('filters')
                                ->prototype('scalar')->end()
                            ->end()
                            ->arrayNode('options')
                                ->prototype('scalar')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('variator')
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->children()
                            ->arrayNode('variants')
                                ->useAttributeAsKey('name')
                                ->prototype('scalar')
//                                    ->prototype('scalar')->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('tools')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('imagine')
                            ->values(['gd', 'imagick', 'gmagick', false])
                            ->defaultValue('gd')
                        ->end()
                        ->scalarNode('convert')->defaultValue($convertBin)->end()
                        ->scalarNode('graphicsmagick')->defaultValue($graphicsMagickBin)->end()
                        ->scalarNode('tesseract')->defaultValue($tesseractBin)->end()
                        ->scalarNode('pdfbox')->defaultValue($pdfBoxBin)->end()
                        ->scalarNode('mediainfo')->defaultValue($mediainfoBin)->end()
                        ->scalarNode('ffmpeg')->defaultValue($ffmpegBin)->end()
                        ->scalarNode('unoconv')->defaultValue($unoconv)->end()
                        ->scalarNode('exiftool')->defaultValue($exiftoolBin)->end()
                        ->scalarNode('jpegoptim')->defaultValue($jpegoptimBin)->end()
                    ->end()
                ->end()
            ->end();
    }
}
