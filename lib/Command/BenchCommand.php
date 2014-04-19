<?php

namespace Regreph\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Regreph\Bench\BenchFile;

class BenchCommand extends Command
{
    const OUTPUT_PLAIN = 'plain';

    const OUTPUT_JSON = 'json';

    const OUPUT_DEFAULT = self::OUTPUT_PLAIN;

    private static $outputFormats = array(
        self::OUTPUT_PLAIN,
        self::OUTPUT_JSON
    );

    protected function configure()
    {
        $this
            ->setName('bench')
            ->setDescription('Benchmark a library with a given testfile')
            ->addArgument(
                'testFile',
                InputArgument::REQUIRED,
                'File with a \Regreph\TestCase class specifying the benchmark'
            )
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_REQUIRED,
                'Ouput format. Possible values: '
                    .implode(', ', static::$outputFormats),
                self::OUPUT_DEFAULT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $out)
    {
        $testFile = $input->getArgument('testFile');
        $benchFile = new BenchFile($testFile);

        $results = $benchFile->getBench()->getResults()->toArray();

        if ($input->getOption('format') === self::OUTPUT_JSON) {
            $output = json_encode($results);
        } elseif ($input->getOption('format') === self::OUTPUT_PLAIN) {
            $output = print_r($results, true);
        }

        $out->writeln($output, OutputInterface::OUTPUT_RAW);
    }
}
