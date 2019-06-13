<?php

declare(strict_types=1);

namespace K911\Swoole\Server\Config;

final class Listener
{
    /**
     * @var Socket
     */
    private $socket;

    /**
     * @var ListenerConfig
     */
    private $config;

    /**
     * @var EventsCallbacks
     */
    private $callbacks;

    /**
     * Port constructor.
     *
     * @param ListenerConfig  $config
     * @param Socket          $socket
     * @param EventsCallbacks $callbacks
     */
    public function __construct(Socket $socket, ListenerConfig $config, EventsCallbacks $callbacks)
    {
        $this->socket = $socket;
        $this->config = $config;
        $this->callbacks = $callbacks;
    }

    public function socket(): Socket
    {
        return $this->socket;
    }

    public function config(): ListenerConfig
    {
        return $this->config;
    }

    public function eventsCallbacks(): EventsCallbacks
    {
        return $this->callbacks;
    }
}
