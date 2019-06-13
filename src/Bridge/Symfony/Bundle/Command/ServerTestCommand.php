<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Bundle\Command;

use K911\Swoole\Server\Api\ApiServerRequestHandler;
use K911\Swoole\Server\Config\EventsCallbacks;
use K911\Swoole\Server\Config\Listener;
use K911\Swoole\Server\Config\ListenerConfig;
use K911\Swoole\Server\Config\Listeners;
use K911\Swoole\Server\Config\ServerFactory;
use K911\Swoole\Server\Config\Socket;
use K911\Swoole\Server\HttpServerConfiguration;
use K911\Swoole\Server\LifecycleHandler\SigIntHandler;
use K911\Swoole\Server\RequestHandler\RequestHandlerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class ServerTestCommand extends Command
{
    private $parameterBag;
    private $configuration;
    private $requestHandler;
    private $sigIntHandler;
    private $apiServerRequestHandler;

    public function __construct(
        ApiServerRequestHandler $apiServerRequestHandler,
        SigIntHandler $sigIntHandler,
        RequestHandlerInterface $requestHandler,
        HttpServerConfiguration $configuration,
        ParameterBagInterface $parameterBag
    )
    {

        parent::__construct();
        $this->configuration = $configuration;
        $this->requestHandler = $requestHandler;
        $this->sigIntHandler = $sigIntHandler;
        $this->parameterBag = $parameterBag;
        $this->apiServerRequestHandler = $apiServerRequestHandler;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setDescription('Test');
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Assert\AssertionFailedException
     * @throws \Throwable
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $exitCode = 0;
        $io = new SymfonyStyle($input, $output);

        // ---
        $mainSocket = new Socket('0.0.0.0', 9501, 'tcp', false);

        $apiSocket = new Socket('0.0.0.0', 9200, 'tcp', false);
        $apiListenerConfig = new ListenerConfig();
        $apiListenerConfig->enableHttp2Protocol();
        $apiListenerConfig->disableHttpProtocol();
        $apiListenerEventsCallbacks = new EventsCallbacks();
        $apiListenerEventsCallbacks->registerRequestHandler($this->apiServerRequestHandler);
        $apiListener = new Listener($apiSocket, $apiListenerConfig, $apiListenerEventsCallbacks);

        $listeners = new Listeners($mainSocket, $apiListener);
        $eventsCallbacks = new EventsCallbacks();
        $eventsCallbacks->registerRequestHandler($this->requestHandler);
        $eventsCallbacks->registerServerStartHandler($this->sigIntHandler);
        $serverFactory = new ServerFactory($listeners, $this->configuration, $eventsCallbacks);
        $server = $serverFactory->make();

        $io->warning(\sprintf('Server class: %s', \get_class($server)));

        // ----

        $io->comment('Quit the server with CONTROL-C.');

        if ($server->start()) {
            $io->newLine();
            $io->success('Swoole HTTP Server has been successfully shutdown.');
        } else {
            $io->error('Failure during starting Swoole HTTP Server.');
        }

        return $exitCode;
    }
}
