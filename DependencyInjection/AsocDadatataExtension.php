<?php

namespace Asoc\DadatataBundle\DependencyInjection;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
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

        $loader->load('examiner.xml');
        $loader->load('descriptor.xml');
        $loader->load('variator.xml');
    }

    private function processToolsSection(LoaderInterface $loader, ContainerBuilder $container, array $config) {
        $ffmpegBin = $config['ffmpeg'];
        $convertBin = $config['convert'];
        $graphicsMagickBin = $config['graphicsmagick'];
        $exiftoolBin = $config['exiftool'];
        $mediainfoBin = $config['mediainfo'];
        $unoconvBin = $config['unoconv'];
        $pdfboxBin = $config['pdfbox'];
        $tesseractBin = $config['tesseract'];

        if($ffmpegBin && is_executable($ffmpegBin)) {
            $container->setParameter('asoc_dadatata.tools.ffmpeg', $ffmpegBin);
        }
        if($convertBin && is_executable($convertBin)) {
            $container->setParameter('asoc_dadatata.tools.convert', $convertBin);
        }
        if($unoconvBin && is_executable($unoconvBin)) {
            $container->setParameter('asoc_dadatata.tools.unoconv', $unoconvBin);
        }
        if($exiftoolBin && is_executable($exiftoolBin)) {
            $container->setParameter('asoc_dadatata.tools.exiftool', $exiftoolBin);
        }
        if($mediainfoBin && is_executable($mediainfoBin)) {
            $container->setParameter('asoc_dadatata.tools.mediainfo', $mediainfoBin);
        }
        if($graphicsMagickBin && is_executable($graphicsMagickBin)) {
            $container->setParameter('asoc_dadatata.tools.graphicsmagick', $graphicsMagickBin);
        }
        if($pdfboxBin && is_executable($pdfboxBin)) {
            $container->setParameter('asoc_dadatata.tools.pdfbox', $pdfboxBin);
        }
        if($tesseractBin && is_executable($tesseractBin)) {
            $container->setParameter('asoc_dadatata.tools.tesseract', $tesseractBin);
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

        if($exiftool) {
            $loader->load('reader/exiftool/image.xml');
            $loader->load('reader/exiftool/pdf.xml');
            $loader->load('reader/exiftool/video_flash.xml');
            $loader->load('reader/exiftool/video_mp4.xml');
            $loader->load('reader/exiftool/audio_vorbis.xml');
            $loader->load('reader/exiftool/audio_mpeg.xml');
        }
        else if($mediainfo) {
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
