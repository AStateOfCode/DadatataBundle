<?php

namespace Asoc\DadatataBundle\DependencyInjection;

use Asoc\Dadatata\Tool\PdfBox;
use Asoc\Dadatata\ToolInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

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
        $config        = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));

        $this->processToolsSection($loader, $container, $config['tools']);

        $this->loadReader($loader, $container);
        $this->loadFilter($loader, $container);
        $this->loadWriter($loader);

        $loader->load('tmpfs.xml');
        $loader->load('descriptor.xml');
        $loader->load('variator.xml');
        $loader->load('type_guesser.xml');
        $loader->load('options.xml');

        foreach ($config['examiner'] as $examinerName => $examinerConfig) {
            $guesser = [];
            foreach ($examinerConfig['type_guesser'] as $guesserName) {
                $guesser[] = new Reference(sprintf('asoc_dadatata.metadata.type_guesser.%s', $guesserName));
            }

            $reader = [];
            foreach ($examinerConfig['reader'] as $readerName) {
                $reader[] = new Reference(sprintf('asoc_dadatata.metadata.reader.aliased.%s', $readerName));
            }

            $examinerId = sprintf('asoc_dadatata.%s_examiner', $examinerName);
            $examiner   = new Definition('Asoc\Dadatata\Metadata\Examiner');
            $examiner->setArguments(
                [
                    $guesser,
                    $reader
                ]
            );

            $container->setDefinition($examinerId, $examiner);
        }

        $container->setParameter('asoc_dadatata.filter.config', $config['filter']);
        $container->setParameter('asoc_dadatata.variator.config', $config['variator']);
    }

    private function processToolsSection(LoaderInterface $loader, ContainerBuilder $container, array $config)
    {
        if (isset($config['imagine'])) {
            $driver = $config['imagine'];
            if ($driver !== false) {
                $loader->load('tools/php/imagine.xml');
                $driverId = sprintf('asoc_dadatata.tools.php.imagine.driver.%s', $driver);

                $driver = $container->getDefinition($driverId);
                if (class_exists($driver->getClass())) {
                    $container->setAlias(
                        'asoc_dadatata.tools.php.imagine.driver',
                        $driverId
                    );
                } else {
                    $container->removeAlias($driverId);
                }
            }

            unset($config['imagine']);
        }

        // process all the CLI programs
        foreach ($config as $name => $value) {
            // completely disabled
            if (false === $value) {
                continue;
            }

            $bin = null;

            // load tool definition
            try {
                $loader->load(sprintf('tools/%s.xml', $name));
            }
            catch(\InvalidArgumentException $e) {
                // no tool class yet, still defined the old way (paths to an executable only)
                continue;
            }

            // no specific path specified, find the executable
            if (null === $value) {
                $toolDefinitionId = sprintf('asoc_dadatata.tools.%s', $name);
                $toolDefinition   = $container->getDefinition($toolDefinitionId);
                $toolClass        = $toolDefinition->getClass();

                // all tool classes should implement ToolInterface
                /** @var ToolInterface $toolClass */
                $tool = $toolClass::create();

                // tool executable was not found so we can remove the service
                if (null === $tool) {
                    $container->removeDefinition($toolDefinitionId);
                } else {
                    $bin = $tool->getBin();
                }
            } // specific path specified, check if it is an executable
            else if (is_executable($value)) {
                $bin = $value;
            }

            if (null !== $bin) {
                $container->setParameter(sprintf('asoc_dadatata.tools.%s.bin', $name), $bin);
            }
        }
    }

    private function loadReader(LoaderInterface $loader, ContainerBuilder $container)
    {
        $ffmpeg         = $container->has('asoc_dadatata.tools.ffmpeg');
        $convert        = $container->has('asoc_dadatata.tools.image_magick_convert');
        $exiftool       = $container->has('asoc_dadatata.tools.exiftool');
        $mediainfo      = $container->has('asoc_dadatata.tools.mediainfo');
        $unoconv        = $container->has('asoc_dadatata.tools.unoconv');
        $graphicsMagick = $container->has('asoc_dadatata.tools.graphicsmagick');

        // reader that are always present
        $loader->load('reader/php/md5.xml');
        $loader->load('reader/php/sha1.xml');
        $loader->load('reader/php/sha512.xml');

        if ($container->hasAlias('asoc_dadatata.tools.php.imagine.driver')) {
            $loader->load('reader/php/imagine.xml');
        }

        if ($exiftool) {
            $loader->load('reader/exiftool/image.xml');
            $loader->load('reader/exiftool/pdf.xml');
            $loader->load('reader/exiftool/video_flash.xml');
            $loader->load('reader/exiftool/video_mp4.xml');
            $loader->load('reader/exiftool/audio_vorbis.xml');
            $loader->load('reader/exiftool/audio_mpeg.xml');
        }
        if ($mediainfo) {
            $loader->load('reader/mediainfo/image.xml');
        }
    }

    private function loadFilter(LoaderInterface $loader, ContainerBuilder $container)
    {
        $loader->load('filter.xml');

        $ffmpeg         = $container->has('asoc_dadatata.tools.ffmpeg');
        $convert        = $container->has('asoc_dadatata.tools.image_magick_convert');
        $exiftool       = $container->has('asoc_dadatata.tools.exiftool');
        $mediainfo      = $container->has('asoc_dadatata.tools.mediainfo');
        $unoconv        = $container->has('asoc_dadatata.tools.unoconv');
        $graphicsMagick = $container->has('asoc_dadatata.tools.graphicsmagick');
        $pdfbox         = $container->has('asoc_dadatata.tools.pdfbox');
        $tesseract      = $container->has('asoc_dadatata.tools.tesseract');
        $jpegoptim      = $container->has('asoc_dadatata.tools.jpegoptim');
        $zbarimg        = $container->has('asoc_dadatata.tools.zbarimg');

        if ($container->hasAlias('asoc_dadatata.tools.php.imagine.driver')) {
            $loader->load('filter/php/imagine_thumbnail.xml');
            $loader->load('filter/php/imagine_resize.xml');
        }

        if ($convert) {
            $loader->load('filter/imagemagick/thumbnail.xml');
            $loader->load('filter/imagemagick/resize.xml');
            $loader->load('filter/imagemagick/pdf_render.xml');
            $loader->load('filter/imagemagick/convert_image.xml');
        }

        if ($ffmpeg) {
            $loader->load('filter/ffmpeg/extract.xml');
        }

        if ($pdfbox) {
            $loader->load('filter/pdfbox/extract_text.xml');
            $loader->load('filter/pdfbox/pdf_to_image.xml');
        }

        if ($unoconv) {
            $loader->load('filter/unoconv/convert.xml');
        }

        if ($tesseract) {
            $loader->load('filter/tesseract/extract_text.xml');
        }

        if ($jpegoptim) {
            $loader->load('filter/jpegoptim/optimize.xml');
        }

        if ($zbarimg) {
            $loader->load('filter/zbar/extract.xml');
        }
    }

    private function loadWriter(LoaderInterface $loader)
    {
        $loader->load('writer/hashes.xml');
        $loader->load('writer/image.xml');
        $loader->load('writer/document.xml');
        $loader->load('writer/video.xml');
        $loader->load('writer/audio.xml');
    }
}
