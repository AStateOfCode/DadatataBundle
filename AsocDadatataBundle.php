<?php

namespace Asoc\DadatataBundle;

use Asoc\DadatataBundle\DependencyInjection\Compiler\FilterPass;
use Asoc\DadatataBundle\DependencyInjection\Compiler\MetadataReaderPass;
use Asoc\DadatataBundle\DependencyInjection\Compiler\MetadataWriterPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class AsocDadatataBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new MetadataReaderPass());
        $container->addCompilerPass(new MetadataWriterPass());
        $container->addCompilerPass(new FilterPass());
    }
}
