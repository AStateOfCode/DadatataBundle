<?php

namespace Asoc\DadatataBundle\Command;

use Asoc\Dadatata\Tool\PdfBox;
use Asoc\Dadatata\Tool\Tesseract;
use Asoc\Dadatata\Tool\Unoconv;
use Asoc\Dadatata\ToolInterface;
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
        $output->writeln('Tools installed:');

        $tools = ['unoconv', 'tesseract', 'pdfbox'];
        foreach($tools as $name) {
            switch($name) {
                case 'unoconv':
                    $tool = Unoconv::create();
                    break;
                case 'tesseract':
                    $tool = Tesseract::create();
                    break;
                case 'pdfbox':
                    $tool = PdfBox::create();
                    break;
                default:
                    $tool = null;
                    break;
            }

            $this->checkTool($output, $name, $tool);
        }
    }

    private function checkTool(OutputInterface $output, $name, ToolInterface $tool = null) {
        if(null === $tool) {
            $output->writeln(sprintf('  %s: <warning>not available</warning>', $name));
        }
        else {
            $output->writeln(sprintf('  %s: <info>%s</info> (%s)', $name, $tool->getVersion(), $tool->getBin()));
        }
    }

} 