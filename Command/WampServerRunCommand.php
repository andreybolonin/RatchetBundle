<?php

namespace Andreybolonin\RatchetBundle\Command;

use Andreybolonin\RatchetBundle\Periodic\PdoPeriodicPing;
use Andreybolonin\RatchetBundle\Ratchet\App;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use React\EventLoop\Factory;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class WampServerRunCommand.
 */
class WampServerRunCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('wamp:server:run')
            ->addOption(
                'host',
                null,
                InputOption::VALUE_REQUIRED,
                'Host of WS instance.',
                '127.0.0.1'
            )
            ->addOption(
                'port',
                null,
                InputOption::VALUE_REQUIRED,
                'Port of WS instance.',
                8095
            )
            ->setDescription('Start Wamp Server');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output);

        if (!class_exists('EvLoop', false) and !class_exists('EventBase', false)) {
            $output->writeln(sprintf($output->isDecorated() ? '<bg=yellow;fg=black;>%s</>' : '%s', 'Install ext-event (libevent) or ext-ev (libev) please. To drastically improve concurrency.'));
        }

        $loop = Factory::create();
        /** @var PdoPeriodicPing $pdoPeriodicPing */
        $pdoPeriodicPing = $this->getContainer()->get('app.pdo_periodic_ping');

        $loop->addPeriodicTimer($pdoPeriodicPing->getTimeout(), function () use ($pdoPeriodicPing) {
            $pdoPeriodicPing->tick();

            // Just code for test
            //$memory = memory_get_usage() / 1024;
            //$formatted = number_format($memory, 3).'K';
            //echo "Current memory usage: {$formatted}\n";
        });

        $websocketHost = $input->getOption('host');
        $websocketPort = $input->getOption('port');
        /*putenv('websocketHost='.$websocketHost);
        putenv('websocketPort='.$websocketPort);*/

        $biddingTopic = $this->getContainer()->get('app.bidding_topic_service');

        /*var_dump(getenv('websocketHost'));
        var_dump(getenv('websocketPort'));*/

        $server = new App($websocketHost, $websocketPort, '0.0.0.0', $loop);
        $server->route('/', $biddingTopic, ['*']);

        // TODO заменить т.к. сообщение не отображает реального факта запуска сервера командой
        $io->success(sprintf('Server listening on ws://%s', $websocketHost.':'.$websocketPort));
        $server->run();
    }
}
