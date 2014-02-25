<?php

namespace Asoc\DadatataBundle\DependencyInjection;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class AsocDadatataExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));

        $this->processToolsSection($loader, $container, $config['tools']);

        $this->loadReader($loader, $container);
        $this->loadFilter($loader, $container);
        $this->loadWriter($loader);

        $loader->load('descriptor.xml');
        $loader->load('variator.xml');
        $loader->load('type_guesser.xml');

        foreach($config['examiner'] as $examinerName => $examinerConfig) {
            $guesser = [];
            foreach($examinerConfig['type_guesser'] as $guesserName) {
                $guesser[] = new Reference(sprintf('asoc_dadatata.metadata.type_guesser.%s', $guesserName));
            }

            $reader = [];
            foreach($examinerConfig['reader'] as $readerName) {
                $reader[] = new Reference(sprintf('asoc_dadatata.metadata.reader.aliased.%s', $readerName));
            }

            $examinerId = sprintf('asoc_dadatata.%s_examiner', $examinerName);
            $examiner = new Definition('Asoc\Dadatata\Metadata\Examiner');
            $examiner->setArguments([
                $guesser,
                $reader
            ]);

            $container->setDefinition($examinerId, $examiner);
        }

        foreach($config['filter'] as $filterName => $filterConfig) {
            $filterId = sprintf('asoc_dadatata.%s_filter', $filterName);
            $templateId = sprintf('asoc_dadatata.filter.aliased.%s', $filterConfig['type']);
            $filter = new DefinitionDecorator($templateId);
            if(isset($filterConfig['options'])) {
                $filter->addMethodCall('setOptions', [$filterConfig['options']]);
            }
            $container->setDefinition($filterId, $filter);
        }

        foreach($config['variator'] as $variatorName => $variatorConfig) {
            $variatorId = sprintf('asoc_dadatata.%s_variator', $variatorName);
            $variator = new Definition('Asoc\Dadatata\SimpleVariator');

            $filters = [];
            foreach($variatorConfig['variants'] as $variant => $filterName) {
                $filters[$variant] = new Reference(sprintf('asoc_dadatata.%s_filter', $filterName));
            }

            $variator->addArgument($filters);
            $container->setDefinition($variatorId, $variator);
        }
    }

    private function processToolsSection(LoaderInterface $loader, ContainerBuilder $container, array $config) {
        if(isset($config['imagine'])) {
            $driver = $config['imagine'];
            if($driver !== false) {
                $loader->load('tools/php/imagine.xml');
                $driverId = sprintf('asoc_dadatata.tools.php.imagine.driver.%s', $driver);

                $driver = $container->getDefinition($driverId);
                if(class_exists($driver->getClass())) {
                    $container->setAlias(
                        'asoc_dadatata.tools.php.imagine.driver',
                        $driverId
                    );
                }
                else {
                    $container->removeAlias($driverId);
                }
            }

            unset($config['imagine']);
        }

        // process all the CLI programs
        foreach($config as $name => $tool) {
            if($tool === false) {
                continue;
            }
            if(is_executable($tool)) {
                $container->setParameter(sprintf('asoc_dadatata.tools.%s', $name), $tool);
            }
        }
    }

    private function loadReader(LoaderInterface $loader, ContainerBuilder $container) {
        $ffmpeg = $container->hasParameter('asoc_dadatata.tools.ffmpeg');
        $convert = $container->hasParameter('asoc_dadatata.tools.convert');
        $exiftool = $container->hasParameter('asoc_dadatata.tools.exiftool');
        $mediainfo = $container->hasParameter('asoc_dadatata.tools.mediainfo');
        $unoconv = $container->hasParameter('asoc_dadatata.tools.unoconv');
        $graphicsMagick = $container->hasParameter('asoc_dadatata.tools.graphicsmagick');

        // reader that are always present
        $loader->load('reader/php/md5.xml');
        $loader->load('reader/php/sha1.xml');
        $loader->load('reader/php/sha512.xml');

        if($container->hasAlias('asoc_dadatata.tools.php.imagine.driver')) {
            $loader->load('reader/php/imagine.xml');
        }

        if($exiftool) {
            $loader->load('reader/exiftool/image.xml');
            $loader->load('reader/exiftool/pdf.xml');
            $loader->load('reader/exiftool/video_flash.xml');
            $loader->load('reader/exiftool/video_mp4.xml');
            $loader->load('reader/exiftool/audio_vorbis.xml');
            $loader->load('reader/exiftool/audio_mpeg.xml');
        }
        if($mediainfo) {
            $loader->load('reader/mediainfo/image.xml');
        }
    }

    private function loadFilter(LoaderInterface $loader, ContainerBuilder $container) {
        $loader->load('filter.xml');

        $ffmpeg = $container->hasParameter('asoc_dadatata.tools.ffmpeg');
        $convert = $container->hasParameter('asoc_dadatata.tools.convert');
        $exiftool = $container->hasParameter('asoc_dadatata.tools.exiftool');
        $mediainfo = $container->hasParameter('asoc_dadatata.tools.mediainfo');
        $unoconv = $container->hasParameter('asoc_dadatata.tools.unoconv');
        $graphicsMagick = $container->hasParameter('asoc_dadatata.tools.graphicsmagick');
        $pdfbox = $container->hasParameter('asoc_dadatata.tools.pdfbox');
        $tesseract = $container->hasParameter('asoc_dadatata.tools.tesseract');

        if($container->hasAlias('asoc_dadatata.tools.php.imagine.driver')) {
            $loader->load('filter/php/imagine_thumbnail.xml');
            $loader->load('filter/php/imagine_resize.xml');
        }

        if($convert) {
            $loader->load('filter/imagemagick/thumbnail.xml');
            $loader->load('filter/imagemagick/resize.xml');
            $loader->load('filter/imagemagick/pdf_render.xml');
        }

        if($ffmpeg) {
            $loader->load('filter/ffmpeg/extract.xml');
        }

        if($pdfbox) {
            $loader->load('filter/pdfbox/extract_text.xml');
        }
    }

    private function loadWriter(LoaderInterface $loader) {
        $loader->load('writer/hashes.xml');
        $loader->load('writer/image.xml');
        $loader->load('writer/document.xml');
        $loader->load('writer/video.xml');
        $loader->load('writer/audio.xml');
    }
}
