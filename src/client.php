<?php

namespace greevex\react\easyServer;

use greevex\react\easyServer\server\easyServerException;
use React;
use Evenement\EventEmitter;
use greevex\react\easyServer\protocol\protocolInterface;

/**
 * Client connection object (with protocol wrapper)
 *
 * Send and receive data between server and client via specified protocol
 * Client emit 'command' event when some command received from client
 * This class is able to use standalone with any stream socket connection
 *
 * @package greevex\react\easyServer
 * @author Gregory Ostrovsky <greevex@gmail.com>
 */
class client
    extends EventEmitter
{

    /**
     * @var React\EventLoop\ExtEventLoop|React\EventLoop\ExtLibeventLoop|React\EventLoop\ExtLibevLoop|React\EventLoop\StreamSelectLoop
     */
    protected $loop;

    /**
     * @var React\Socket\Connection
     */
    protected $clientConnection;

    /**
     * @var protocolInterface
     */
    protected $protocol;

    /**
     * @var string
     */
    protected $id;

    /**
     * Initialize new client object wrapped by specified protocol
     *
     * @param React\Socket\Connection|resource $socket Stream socket resource of connection
     * @param React\EventLoop\LoopInterface    $loop
     * @param protocolInterface                $protocol
     *
     * @throws \greevex\react\easyServer\server\easyServerException
     */
    public function __construct($socket, React\EventLoop\LoopInterface $loop, protocolInterface $protocol)
    {
        if(is_resource($socket)) {
            $this->clientConnection = new React\Socket\Connection($socket, $loop);
        } elseif(is_a($socket, React\Socket\ConnectionInterface::class)) {
            $this->clientConnection = $socket;
        } else {
            throw new easyServerException('Invalid connection: ' . gettype($socket) . ' given. Allowed types: resource, ' . React\Socket\ConnectionInterface::class . '.');
        }
        $this->clientConnection->pause();
        $this->loop = $loop;
        $this->protocol = $protocol;

        $this->id = stream_socket_get_name($this->clientConnection->stream, false)
            . '<->'
            . stream_socket_get_name($this->clientConnection->stream, true);

        $this->protocol->on('command', function($command) {
            $this->emit('command', [$command, $this]);
        });

        $this->clientConnection->on('data', [$this->protocol, 'onData']);

        $this->clientConnection->on('error', function($error) {
            $this->emit('error', [$error, $this]);
        });
        $this->clientConnection->on('close', function() {
            $this->emit('close', [$this]);
            $this->clientConnection->removeAllListeners();
            $this->protocol->removeAllListeners();
            $this->removeAllListeners();
        });
        $this->clientConnection->resume();
    }

    /**
     * Pause processing client events
     */
    public function pause()
    {
        $this->clientConnection->pause();
    }

    /**
     * Resume processing client events
     */
    public function resume()
    {
        $this->clientConnection->resume();
    }

    /**
     * Send some command to client via specified protocol
     *
     * @param $command
     *
     * @return bool|void
     */
    public function send($command)
    {
        $toSend = $this->protocol->prepareCommand($command);

        return $this->clientConnection->write($toSend);
    }

    /**
     * Disconnect client
     */
    public function disconnect()
    {
        $this->clientConnection->close();
    }

    /**
     * Get unique client id
     *
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }
}