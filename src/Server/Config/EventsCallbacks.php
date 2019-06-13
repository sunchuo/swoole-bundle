<?php

declare(strict_types=1);

namespace K911\Swoole\Server\Config;

use Assert\Assertion;
use K911\Swoole\Server\LifecycleHandler\ServerStartHandlerInterface;
use K911\Swoole\Server\RequestHandler\RequestHandlerInterface;

final class EventsCallbacks
{
    public const EVENT_START = 'start';
    public const EVENT_SHUTDOWN = 'shutdown';
    public const EVENT_WORKER_START = 'workerstart';
    public const EVENT_WORKER_STOP = 'workerstop';
    public const EVENT_WORKER_EXIT = 'workerexit';
    public const EVENT_CONNECT = 'connect';
    public const EVENT_RECEIVE = 'receive';
    public const EVENT_PACKET = 'packet';
    public const EVENT_CLOSE = 'close';
    public const EVENT_TASK = 'task';
    public const EVENT_FINISH = 'finish';
    public const EVENT_PIPE_MESSAGE = 'pipemessage';
    public const EVENT_WORKER_ERROR = 'workererror';
    public const EVENT_MANAGER_START = 'managerstart';
    public const EVENT_MANAGER_STOP = 'managerstop';
    public const EVENT_REQUEST = 'request';
    public const EVENT_OPEN = 'open';
    public const EVENT_HANDSHAKE = 'handshake';
    public const EVENT_MESSAGE = 'message';

    /**
     * Possible events to register callbacks on swoole server instances.
     *
     * @see https://wiki.swoole.com/wiki/page/41.html
     * @see \Swoole\Server
     * @see https://wiki.swoole.com/wiki/page/41.html
     * @see \Swoole\Http\Server
     * @see https://wiki.swoole.com/wiki/page/400.html
     * @see \Swoole\WebSocket\Server
     */
    public const SERVER_EVENTS_BY_TYPE = [
        self::EVENT_START => ServerFactory::SERVER_TYPE_TRANSPORT,
        self::EVENT_SHUTDOWN => ServerFactory::SERVER_TYPE_TRANSPORT,
        self::EVENT_WORKER_START => ServerFactory::SERVER_TYPE_TRANSPORT,
        self::EVENT_WORKER_STOP => ServerFactory::SERVER_TYPE_TRANSPORT,
        self::EVENT_WORKER_EXIT => ServerFactory::SERVER_TYPE_TRANSPORT,
        self::EVENT_CONNECT => ServerFactory::SERVER_TYPE_TRANSPORT,
        self::EVENT_RECEIVE => ServerFactory::SERVER_TYPE_TRANSPORT,
        self::EVENT_PACKET => ServerFactory::SERVER_TYPE_TRANSPORT,
        self::EVENT_CLOSE => ServerFactory::SERVER_TYPE_TRANSPORT,
        self::EVENT_TASK => ServerFactory::SERVER_TYPE_TRANSPORT,
        self::EVENT_FINISH => ServerFactory::SERVER_TYPE_TRANSPORT,
        self::EVENT_PIPE_MESSAGE => ServerFactory::SERVER_TYPE_TRANSPORT,
        self::EVENT_WORKER_ERROR => ServerFactory::SERVER_TYPE_TRANSPORT,
        self::EVENT_MANAGER_START => ServerFactory::SERVER_TYPE_TRANSPORT,
        self::EVENT_MANAGER_STOP => ServerFactory::SERVER_TYPE_TRANSPORT,
        self::EVENT_REQUEST => ServerFactory::SERVER_TYPE_HTTP,
        self::EVENT_OPEN => ServerFactory::SERVER_TYPE_WEBSOCKET,
        self::EVENT_HANDSHAKE => ServerFactory::SERVER_TYPE_WEBSOCKET,
        self::EVENT_MESSAGE => ServerFactory::SERVER_TYPE_WEBSOCKET,
    ];

    private $registeredEvents;

    public function __construct(array $registeredEvents = [])
    {
        $this->registeredEvents = $registeredEvents;
    }

    public function register(string $event, callable $eventCallback, int $priority = 100): void
    {
        $event = \strtolower($event);
        Assertion::keyExists(self::SERVER_EVENTS_BY_TYPE, $event, 'Event name "%s" is invalid.');

        $callbackPriorityPair = [$eventCallback, $priority];
        if (!\array_key_exists($event, $this->registeredEvents)) {
            $this->registeredEvents[$event] = [$callbackPriorityPair];

            return;
        }

        $this->registeredEvents[$event][] = $callbackPriorityPair;
    }

    public function inferServerType(): string
    {
        if (\array_key_exists(self::EVENT_MESSAGE, $this->registeredEvents)) {
            return ServerFactory::SERVER_TYPE_WEBSOCKET;
        }

        if (\array_key_exists(self::EVENT_REQUEST, $this->registeredEvents)) {
            return ServerFactory::SERVER_TYPE_HTTP;
        }

        return ServerFactory::SERVER_TYPE_TRANSPORT;
    }

    /**
     * @return \Generator&iterable<string, callable>
     */
    public function get(): iterable
    {
        foreach ($this->registeredEvents as $eventName => $callbackPriorityPairGroup) {
            $count = \count($callbackPriorityPairGroup);

            if (0 === $count) {
                continue;
            }

            if (1 === $count) {
                yield $eventName => $callbackPriorityPairGroup[0][0];
                continue;
            }

            \usort($callbackPriorityPairGroup, function (array $callbackPriorityPairOne, array $callbackPriorityPairTwo) {
                return $callbackPriorityPairOne[1] <=> $callbackPriorityPairTwo[1];
            });

            /** @var callable[] $callbackSortedGroup */
            $callbackSortedGroup = \array_map(function (array $callbackPriorityPair): callable {
                return $callbackPriorityPair[0];
            }, $callbackPriorityPairGroup);

            yield $eventName => function (...$args) use ($callbackSortedGroup): void {
                foreach ($callbackSortedGroup as $callback) {
                    $callback(...$args);
                }
            };
        }
    }

    public function registerRequestHandler(RequestHandlerInterface $requestHandler, int $priority = 100): void
    {
        $this->register(self::EVENT_REQUEST, [$requestHandler, 'handle'], $priority);
    }

    public function registerServerStartHandler(ServerStartHandlerInterface $serverStartHandler, int $priority = 100): void
    {
        $this->register(self::EVENT_START, [$serverStartHandler, 'handle'], $priority);
    }

    // TBD
}
