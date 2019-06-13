<?php

declare(strict_types=1);

namespace K911\Swoole\Server\Config;

use Assert\Assertion;
use K911\Swoole\Server\HttpServerConfiguration;
use Swoole\Http\Server as SwooleHttpServer;
use Swoole\Server as SwooleServer;
use Swoole\WebSocket\Server as SwooleWebsocketServer;

final class ServerFactory
{
    public const SERVER_TYPE_TRANSPORT = 'transport';
    public const SERVER_TYPE_HTTP = 'http';
    public const SERVER_TYPE_WEBSOCKET = 'websocket';

    public const SERVER_CLASS_BY_TYPE = [
        self::SERVER_TYPE_WEBSOCKET => SwooleWebsocketServer::class,
        self::SERVER_TYPE_HTTP => SwooleHttpServer::class,
        self::SERVER_TYPE_TRANSPORT => SwooleServer::class,
    ];

    private const SWOOLE_RUNNING_MODE = [
        'process' => SWOOLE_PROCESS,
        'reactor' => SWOOLE_BASE,
    ];

    private $listeners;
    private $configuration;
    private $callbacks;

    public function __construct(Listeners $listeners, HttpServerConfiguration $configuration, EventsCallbacks $callbacks)
    {
        $this->listeners = $listeners;
        $this->configuration = $configuration;
        $this->callbacks = $callbacks;
    }

    /**
     * @return \Swoole\Server|\Swoole\Http\Server|\Swoole\WebSocket\Server
     */
    private function createServerInstance(): object
    {
        $runningMode = self::SWOOLE_RUNNING_MODE[$this->configuration->getRunningMode()];
        $serverClass = self::SERVER_CLASS_BY_TYPE[$this->inferredType()];
        $mainSocket = $this->listeners->mainSocket();

        return new $serverClass($mainSocket->host(), $mainSocket->port(), $runningMode, $mainSocket->type());
    }

    /**
     * @return \Swoole\Server|\Swoole\Http\Server|\Swoole\WebSocket\Server
     */
    public function make(): object
    {
        $server = $this->createServerInstance();
        $server->set($this->configuration->getSwooleSettings());

        $mainSocket = $this->listeners->mainSocket();
        if (0 === $mainSocket->port()) {
            $this->listeners->changeMainSocket($mainSocket->withPort($server->port));
        }

        /** @var callable[] $callbacks */
        $callbacks = $this->callbacks->get();
        foreach ($callbacks as $eventName => $callback) {
            Assertion::isCallable($callback, \sprintf('Callback for event "%s" is not a callable. Actual type: %s', $eventName, \gettype($callback)));
            $server->on($eventName, $callback);
        }

        /** @var Listener[] $listeners */
        $listeners = $this->listeners->get();
        foreach ($listeners as $listener) {
            $socket = $listener->socket();
            /** @var \Swoole\Server\Port $port */
            $port = $server->listen($socket->host(), $socket->port(), $socket->type());
            $port->set($listener->config()->all());
            foreach ($listener->eventsCallbacks()->get() as $eventName => $callback) {

                Assertion::isCallable($callback, \sprintf('Callback for event "%s" is not a callable. Actual type: %s', $eventName, \gettype($callback)));
                $server->on($eventName, $callback);
            }
        }

        return $server;
    }

    public function inferredType(): string
    {
        return self::inferType([$this->callbacks->inferServerType(), $this->listeners->inferServerType()]);
    }

    public static function inferType(iterable $types): string
    {
        $inferredType = self::SERVER_TYPE_TRANSPORT;
        foreach ($types as $type) {
            if (self::SERVER_TYPE_WEBSOCKET === $type) {
                return self::SERVER_TYPE_WEBSOCKET;
            }

            if (self::SERVER_TYPE_HTTP === $type) {
                $inferredType = self::SERVER_TYPE_HTTP;
            }
        }

        return $inferredType;
    }
}
