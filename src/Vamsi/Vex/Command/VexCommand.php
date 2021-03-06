<?php

namespace Vamsi\Vex\Command;

use GuzzleHttp\Pool;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use GuzzleHttp\TransferStats;
use Symfony\Component\Console\Input\InputArgument;

class VexCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('vex')
            ->setDescription('make the specified requests')
            ->setHelp('This command sends the specified requests to the specified URL')
            ->addArgument('url', InputArgument::REQUIRED, 'The URL to which the requests should be sent')
            ->addArgument('n', InputArgument::OPTIONAL, 'Number of requests to be made', 1)
            ->addArgument('c', InputArgument::OPTIONAL, 'Concurrency', 1)
            ->addArgument('m', InputArgument::OPTIONAL, 'HTTP Method', 'GET');

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $array = [];
        $url = $input->getArgument('url');
        $number_of_requests = $input->getArgument('n');
        $concurrency = $input->getArgument('c');
        $http_method = $input->getArgument('m');

        $output->writeln("Sending $number_of_requests requests with $concurrency Concurrency");
        $client = new Client([
            'on_stats' => function (TransferStats $stats) use (&$array) {
                $time = $stats->getTransferTime();
                $array[] = $time;
                //echo $time.PHP_EOL;
                //$stats->getHandlerStats();
            }
        ]);

        $progress = new ProgressBar($output, $number_of_requests);

        $progress->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $progress->start();
        $requests = function ($total) use ($url, $http_method) {
            $uri = $url;
            for ($i = 0; $i < $total; $i++) {
                yield new Request($http_method, $uri);
            }
        };

        $pool = new Pool($client, $requests($number_of_requests), [
            'concurrency' => $concurrency,
            'fulfilled' => function ($response, $index) use ($progress) {
                $progress->advance();
            },
            'rejected' => function ($reason, $index) {
                // this is delivered each failed request
            },
        ]);

        $promise = $pool->promise();
        $promise->wait();
        $progress->finish();
        $output->writeln('');
        $output->writeln('Done!');

    }
}