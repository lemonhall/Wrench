<?php
namespace Wrench;

use Wrench\Protocol\Protocol;
use Wrench\Resource;
use Wrench\Util\Configurable;
use Wrench\Exception\Exception as WrenchException;
use Wrench\Exception\CloseException;
use \Exception;

class ConnectionManager extends Configurable
{
    const TIMEOUT_SELECT          = 0;
    const TIMEOUT_SELECT_MICROSEC = 200000;

    /**
     * @var Server
     */
    protected $server;

    /**
     * Master socket
     *
     * @var Socket
     */
    protected $socket;

    /**
     * An array of client connections
     *
     * @var array<int => Connection>
     */
    protected $connections = array();

    /**
     * An array of raw socket resources, corresponding to connections, roughly
     *
     * @var array<int => resource>
     */
    protected $resources = array();

    /**
     * Constructor
     *
     * @param Server $server
     * @param array $options
     */
    public function __construct(Server $server, array $options = array())
    {
        $this->server = $server;

        parent::__construct($options);
    }

    /**
     * @see Wrench\Socket.Socket::configure()
     *   Options include:
     *     - timeout_select          => int, seconds, default 0
     *     - timeout_select_microsec => int, microseconds (NB: not milli), default: 200000
     *     - timeout_accept          => int, seconds, default 5
     */
    protected function configure(array $options)
    {
        $options = array_merge(array(
            'socket_master_class'     => 'Wrench\Socket\ServerSocket',
            'socket_master_options'   => array(),
            'connection_class'        => 'Wrench\Connection',
            'connection_options'      => array(),
            'timeout_select'          => self::TIMEOUT_SELECT,
            'timeout_select_microsec' => self::TIMEOUT_SELECT_MICROSEC

        ), $options);

        parent::configure($options);

        $this->configureSocket();
    }

    /**
     * Gets the application associated with the given path
     *
     * @param string $path
     */
    public function getApplicationForPath($path)
    {
        $path = ltrim($path, '/');
        return $this->server->getApplication($path);
    }

    /**
     * Configures the main server socket
     *
     * @param string $uri
     */
    protected function configureSocket()
    {
        $class   = $this->options['socket_master_class'];
        $options = $this->options['socket_master_options'];
        $this->socket = new $class($this->server->getUri(), $options);
    }


    /**
     * Listens on the main socket
     *
     * @return void
     */
    public function listen()
    {
        $this->socket->listen();
        $this->resources[$this->socket->getResourceId()] = $this->socket->getResource();
    }

    /**
     * Gets all resources
     *
     * @return array<int => resource)
     */
    protected function getAllResources()
    {
        return array_merge($this->resources, array(
            $this->socket->getResourceId() => $this->socket->getResource()
        ));
    }

    /**
     * Returns the Connection associated with the specified socket resource
     *
     * @param resource $socket
     * @return Connection
     */
    protected function getConnectionForClientSocket($socket)
    {
        if (!isset($this->connections[$this->resourceId($socket)])) {
            return false;
        }
        return $this->connections[$this->resourceId($socket)];
    }

    /**
     * Select and process an array of resources
     *
     * @param array $resources
     */
    public function selectAndProcess()
    {
        $read             = $this->resources;
        $unused_write     = null;
        $unsued_exception = null;

        stream_select(
            $read,
            $unused_write,
            $unused_exception,
            $this->options['timeout_select'],
            $this->options['timeout_select_microsec']
        );

        foreach ($read as $socket) {
            if ($socket == $this->socket->getResource()) {
                $this->processMasterSocket();
            } else {
                $this->processClientSocket($socket);
            }
        }
    }

    /**
     * Process events on the master socket ($this->socket)
     *
     * @return void
     */
    protected function processMasterSocket()
    {
        $new = null;

        try {
            $new = $this->socket->accept();
        } catch (Exception $e) {
            $this->server->log('Socket error: ' . $e, 'err');
            return;
        }

        $connection = $this->createConnection($new);
        $this->server->notify(Server::EVENT_SOCKET_CONNECT, array($new, $connection));
    }

    /**
     * Creates a connection from a socket resource
     *
     * The create connection object is based on the options passed into the
     * constructor ('connection_class', 'connection_options'). This connection
     * instance and its associated socket resource are then stored in the
     * manager.
     *
     * @param resource $resource A socket resource
     * @return Connection
     */
    protected function createConnection($resource)
    {
        if (!$resource || !is_resource($resource)) {
            throw new InvalidArgumentException('Invalid connection resource');
        }

        $class = $this->options['connection_class'];
        $options = $this->options['connection_options'];

        $connection = new $class($this, $resource, $options);

        $id = $this->resourceId($resource);
        $this->resources[$id] = $resource;
        $this->connections[$id] = $connection;

        return $connection;
    }

    /**
     * Process events on a client socket
     *
     * @param resource $socket
     */
    protected function processClientSocket($socket)
    {
        $connection = $this->getConnectionForClientSocket($socket);

        if (!$connection) {
            $this->log('No connection for client socket', 'warning');
            return;
        }

        try {
            $connection->process();
        } catch (WrenchException $e) {
            $this->log('Error on client socket: ' . $e, 'err');
            $connection->close($e);
            //$this->removeConnection($connection); // extra?
        }
    }

    /**
     * This server makes an explicit assumption: PHP resource types may be cast
     * to a integer. Furthermore, we assume this is bijective. Both seem to be
     * true in most circumstances, but may not be guaranteed.
     *
     * This method (and $this->getResourceId()) exist to make this assumption
     * explicit.
     *
     * This is needed on the connection manager as well as on resources
     *
     * @param resource $resource
     */
    protected function resourceId($resource)
    {
        return (int)$resource;
    }

    /**
     * Gets the connection manager's listening URI
     *
     * @return string
     */
    public function getUri()
    {
        return $this->server->getUri();
    }

    /**
     * Logs a message
     *
     * @param string $message
     * @param string $priority
     */
    public function log($message, $priority = 'info')
    {
        $this->server->log(sprintf(
            '%s: %s',
            __CLASS__,
            $message
        ), $priority);
    }

    /**
     * @return \Wrench\Server
     */
    public function getServer()
    {
        return $this->server;
    }

    /**
     * Removes a connection
     *
     * @param Connection $connection
     */
    public function removeConnection(Connection $connection)
    {
        $socket = $connection->getSocket();

        if ($socket->getResource()) {
            $index = $socket->getResourceId();
        } else {
            $index = array_search($connection, $this->connections);
        }

        if (!$index) {
            $this->log('Could not remove connection: not found', 'warning');
        }

        unset($this->connections[$index]);
        unset($this->resources[$index]);

        $this->server->notify(
            Server::EVENT_SOCKET_DISCONNECT,
            array($connection->getSocket())
        );
    }

    /**
     * Removes a client from client storage.
     *
     * @param Object $client Client object.
     * @deprecated
     */
    public function removeClientOnClose($client)
    {
        throw new \Exception('Deprecated: just removeConnection');
//         $clientId = $client->getClientId();
//         $clientIp = $client->getClientIp();
//         $clientPort = $client->getClientPort();
//         $resource = $client->getClientSocket();

//         $this->_removeIpFromStorage($client->getClientIp());
//         if(isset($this->_requestStorage[$clientId]))
//         {
//             unset($this->_requestStorage[$clientId]);
//         }
//         unset($this->connections[(int)$resource]);
//         $index = array_search($resource, $this->resources);
//         unset($this->resources[$index], $client);

//         // trigger status application:
//         if($this->getApplication('status') !== false)
//         {
//             $this->getApplication('status')->clientDisconnected($clientIp, $clientPort);
//         }
//         unset($clientId, $clientIp, $clientPort, $resource);
    }

    /**
     * Removes a client and all references in case of timeout/error.
     * @param object $client The client object to remove.
     * @deprecated
     */
    public function removeClientOnError($client)
    {
        throw new \Exception('Deprecated: just removeConnection');
        // remove reference in clients app:
//         if($client->getClientApplication() !== false)
//         {
//             $client->getClientApplication()->onDisconnect($client);
//         }

//         $resource = $client->getClientSocket();
//         $clientId = $client->getClientId();
//         $clientIp = $client->getClientIp();
//         $clientPort = $client->getClientPort();
//         $this->_removeIpFromStorage($client->getClientIp());
//         if(isset($this->_requestStorage[$clientId]))
//         {
//             unset($this->_requestStorage[$clientId]);
//         }
//         unset($this->connections[(int)$resource]);


//         // trigger status application:
//         if($this->getApplication('status') !== false)
//         {
//             $this->getApplication('status')->clientDisconnected($clientIp, $clientPort);
//         }
//         unset($resource, $clientId, $clientIp, $clientPort);
    }
}
