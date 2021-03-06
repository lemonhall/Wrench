<!-- vim: set tw=79 sw=4 ts=4 et ft=markdown : -->
# Wrench
## Simple WebSocket Client/Server for PHP
### Formerly known as php-websocket

Version: **2.0.0-beta**

A simple websocket server and client package for PHP 5.3/5.4, using
streams.

### Features

- Supports websocket draft hybi-10,13 (Currently tested with Chrome 20 and
  Firefox 11).
- Supports origin-check.
- Supports various security/performance settings.
- Supports binary frames. (Currently receive only)
- Supports wss. (Needs valid certificate in Firefox.)

### Backward compatibility

#### Why the name change?

See [Frequently Asked Questions about the PHP License](http://php.net/license/index.php#fac-lic).
Also, the namespace WebSocket is too generic; it denotes a common functionality,
and may already be in use by application code. The BC break of a new
[major version](http://semver.org/) was a good time to introduce this move
to best practices.

#### Public API

The new vendor namespace is Wrench. This namespace begins in the `/lib`
directory, rather than `server/lib`.

Apart from the new namespace, the public API of this new major version is
almost completely compatible with that of php-websocket 1.0.0.

#### Protected API

The protected API has changed, a lot. Many method have been broken up into
simple protected methods. This makes the Server class much easier to extend. In
fact, almost all of the classes involved in your typical daemon can now be
replaced or extended, including the socket handling and protocol handling.

#### What happened to the `client` dir?

The client-side libraries are no longer supported: some libraries are included
but are packaged only as examples. You're free to use whatever client-side
libraries you'd like with the server. If you're still using them, see the 1.0
branch.

## Installation

The library is PSR-0 compatible, with a vendor name of **Wrench**. An
SplClassLoader is bundled for convenience.

## Usage

This creates a server on 127.0.0.1:8000 with one Application that listens for
WebSocket requests to `ws://localhost:8000/echo` and `ws://localhost:8000/chat`:

```php
$server = new \Wrench\BasicServer('ws://localhost:8000', array(
    'allowed_origins' => array(
        'mysite.com',
        'mysite.dev.localdomain'
    )
));
$server->registerApplication('echo', new \Wrench\Examples\EchoApplication());
$server->registerApplication('chat', new \My\ChatApplication());
$server->run();
```
## Authors

The original maintainer and author was
[@nicokaiser](https://github.com/nicokaiser). Plentiful improvements were
contributed by [@lemmingzshadow](https://github.com/lemmingzshadow) and
[@mazhack](https://github.com/mazhack). Parts of the Socket class were written
by Moritz Wutz. The server is licensed under the WTFPL, a free software compatible
license.

## Bugs/Todos/Hints

- Add tests around fragmented payloads (split into many frames).
- To report issues, see the [issue tracker](https://github.com/varspool/Wrench/issues).

## Examples

- See server.php in the examples directory and
  Wrench\Application\EchoApplication
- [Jitt.li](http://jitt.li), a Twitter API sample project.
- For Symfony2, [VarspoolWebsocketBundle](https://github.com/varspool/WebsocketBundle)
  extends this library for use with the Service Container.
