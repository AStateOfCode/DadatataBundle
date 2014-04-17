<?php

namespace Asoc\DadatataBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ToolCheckCommand extends ContainerAwareCommand {

    protected function configure()
    {
        $this->setName('dadatata:tool-check')
            ->setDescription('Check available tools');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $tools = ['unoconv', 'tesseract', 'pdfbox'];
        $container = $this->getContainer();

        $output->writeln('Tools installed:');

        foreach($tools as $name) {
            $toolServiceId = sprintf('asoc_dadatata.tools.%s', $name);
            if(!$container->has($toolServiceId)) {
                $output->writeln(sprintf('  %s: <comment>not available</comment>', $name));
                continue;
            }

            $tool = $container->get($toolServiceId);
            $version = $tool->getVersion();

            if(false === $version) {
                $output->writeln(sprintf('  %s: failed to retrieve version (%s)', $name, $tool->getBin()));
            }
            else if(null === $version) {
                $output->writeln(sprintf('  %s: no version info (%s)', $name, $tool->getBin()));
            }
            else {
                $output->writeln(sprintf('  %s: <info>%s</info> (%s)', $name, $tool->getVersion(), $tool->getBin()));
            }
        }
    }

} 