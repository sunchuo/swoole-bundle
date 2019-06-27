<?php

declare(strict_types=1);

namespace K911\Swoole\Server\Config;

use Assert\Assertion;
use K911\Swoole\Server\ServerFactory;

final class Listeners
{
    private $mainSocket;
    private $mainSocketPort;
    private $listenersMappedByItsPorts;

    public function __construct(Socket $mainSocket, Listener ...$additionalListeners)
    {
        $this->mainSocket = $mainSocket;
        $this->mainSocketPort = $mainSocket->port();
        $this->listenersMappedByItsPorts = [];
        $this->addListeners(...$additionalListeners);
    }

    public function addListeners(Listener ...$listeners): void
    {
        foreach ($listeners as $listener) {
            $listenerPort = $listener->socket()->port();
            Assertion::notEq($this->mainSocketPort, $listenerPort, 'Port "%s" is already registered as main server listener.');
            Assertion::keyNotExists($this->listenersMappedByItsPorts, $listenerPort, 'Port "%s" has already been registered.');
            $this->listenersMappedByItsPorts[$listenerPort] = $listener;
        }
    }

    public function changeMainSocket(Socket $socket): void
    {
        Assertion::keyNotExists($this->listenersMappedByItsPorts, $socket->port(), 'Port "%s" cannot be used as main server listener because it has already been registered.');
        $this->mainSocket = $socket;
    }

    public function mainSocket(): Socket
    {
        return $this->mainSocket;
    }

    /**
     * @return iterable<Listener>
     */
    public function get(): iterable
    {
        foreach ($this->listenersMappedByItsPorts as $port => $listener) {
            yield $listener;
        }
    }

    public function portsInferredServerTypes(): iterable
    {
        /** @var Listener $listener */
        foreach ($this->get() as $listener) {
            yield $listener->eventsCallbacks()->inferServerType();
        }
    }

    public function inferServerType(): string
    {
        return ServerFactory::inferType($this->portsInferredServerTypes());
    }
}
