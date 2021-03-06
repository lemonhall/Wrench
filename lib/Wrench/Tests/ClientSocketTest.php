<?php

namespace Wrench\Tests;

use Wrench\Protocol\Rfc6455Protocol;

use Wrench\Socket\ClientSocket;

use \stdClass;
use \InvalidArgumentException;
use \PHPUnit_Framework_Error;

class ClientSocketTest extends Test
{
    public function getClass()
    {
        return 'Wrench\Socket\ClientSocket';
    }

    public function testConstructor()
    {
        $socket = null;

        $this->assertInstanceOfClass(
            $socket = new ClientSocket('ws://localhost/'),
            'ws:// scheme, default port'
        );

        $this->assertInstanceOfClass(
            $socket = new ClientSocket('ws://localhost/some-arbitrary-path'),
            'with path'
        );

        $this->assertInstanceOfClass(
            $socket = new ClientSocket('wss://localhost/test', array()),
            'empty options'
        );

        $this->assertInstanceOfClass(
            $socket = new ClientSocket('ws://localhost:8000/foo'),
            'specified port'
        );
    }

    public function testOptions()
    {
        $socket = null;

        $this->assertInstanceOfClass(
            $socket = new ClientSocket(
                'ws://localhost:8000/foo', array(
                    'timeout_connect' => 10
                )
            ),
            'connect timeout'
        );

        $this->assertInstanceOfClass(
            $socket = new ClientSocket(
                'ws://localhost:8000/foo', array(
                    'timeout_socket' => 10
                )
            ),
            'socket timeout'
        );

        $this->assertInstanceOfClass(
            $socket = new ClientSocket(
                'ws://localhost:8000/foo', array(
                    'protocol' => new Rfc6455Protocol()
                )
            ),
            'protocol'
        );
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testProtocolTypeError()
    {
        $socket = new ClientSocket(
            'ws://localhost:8000/foo', array(
                'protocol' => new stdClass()
            )
        );
    }

    /**
     * @expectedException PHPUnit_Framework_Error
     */
    public function testConstructorUriUnspecified()
    {
        $w = new ClientSocket();
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testConstructorUriEmpty()
    {
        $w = new ClientSocket(null);
    }


    /**
     * @expectedException InvalidArgumentException
     */
    public function testConstructorUriInvalid()
    {
        $w = new ClientSocket('Bad argument');
    }

}
