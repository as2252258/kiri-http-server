<?php

namespace Kiri\Server\Abstracts;

use Exception;
use Kiri\Abstracts\Component;
use Kiri\Config\ConfigProvider;
use Kiri\Router\DataGrip;
use Kiri\Router\Handler;
use Kiri\Router\RouterCollector;
use Kiri\Server\Constant;
use Kiri\Server\Contract\OnCloseInterface;
use Kiri\Server\Contract\OnDisconnectInterface;
use Kiri\Server\Contract\OnHandshakeInterface;
use Kiri\Server\Contract\OnMessageInterface;
use ReflectionException;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Server;
use Swoole\WebSocket\Frame;


class WebSocket extends Component implements OnHandshakeInterface, OnMessageInterface, OnDisconnectInterface, OnCloseInterface
{


    public Handler $handler;


    /**
     * @var RouterCollector
     */
    public RouterCollector $collector;


    /**
     * @return void
     * @throws ReflectionException
     */
    public function init(): void
    {
        $this->collector = \Kiri::getDi()->get(DataGrip::class)->get('wss');

        $this->handler = $this->collector->query('/', 'GET');
        $config = \Kiri::getDi()->get(ConfigProvider::class);
        if ($this->handler->implement(OnHandshakeInterface::class)) {
            $config->modify('server.ports.events.' . Constant::HANDSHAKE, [$this->handler->getClass(), 'onHandshake']);
        }
        if ($this->handler->implement(OnMessageInterface::class)) {
            $config->modify('server.ports.events.' . Constant::MESSAGE, [$this->handler->getClass(), 'onMessage']);
        }
        if ($this->handler->implement(OnDisconnectInterface::class)) {
            $config->modify('server.ports.events.' . Constant::DISCONNECT, [$this->handler->getClass(), 'onDisconnect']);
        }
        if ($this->handler->implement(OnCloseInterface::class)) {
            $config->modify('server.ports.events.' . Constant::CLOSE, [$this->handler->getClass(), 'onClose']);
        }
    }


    /**
     * @param Request $request
     * @param Response $response
     * @return void
     * @throws Exception
     */
    public function onHandshake(Request $request, Response $response): void
    {
        // TODO: Implement onHandshake() method.
        $secWebSocketKey = $request->header['sec-websocket-key'];
        $patten = '#^[+/0-9A-Za-z]{21}[AQgw]==$#';
        if (0 === preg_match($patten, $secWebSocketKey) || 16 !== strlen(base64_decode($secWebSocketKey))) {
            throw new Exception('protocol error.', 500);
        }
        $key = base64_encode(sha1($request->header['sec-websocket-key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', TRUE));
        $headers = [
            'Upgrade'               => 'websocket',
            'Connection'            => 'Upgrade',
            'Sec-websocket-Accept'  => $key,
            'Sec-websocket-Version' => '13',
        ];
        if (isset($request->header['sec-websocket-protocol'])) {
            $explode = explode(',', $request->header['sec-websocket-protocol']);
            $headers['Sec-websocket-Protocol'] = $explode[0];
        }
        foreach ($headers as $key => $val) {
            $response->setHeader($key, $val);
        }
        if ($this->handler->implement(OnHandshakeInterface::class)) {
            $handler = $this->handler->getClass();

            call_user_func([$handler, 'onHandshake'], $request, $response);
        } else {
            $response->setStatusCode(101);
            $response->end();
        }
    }


    /**
     * @param Server $server
     * @param Frame $frame
     * @return void
     */
    public function onMessage(Server $server, Frame $frame): void
    {
        // TODO: Implement onMessage() method.
        if (!$this->handler->implement(OnMessageInterface::class)) {
            return;
        }

        $handler = $this->handler->getClass();

        call_user_func([$handler, 'onMessage'], $server, $frame);
    }


    /**
     * @param \Swoole\WebSocket\Server $server
     * @param int $fd
     * @return void
     */
    public function onDisconnect(\Swoole\WebSocket\Server $server, int $fd): void
    {
        // TODO: Implement onDisconnect() method.
        if (!$this->handler->implement(OnDisconnectInterface::class)) {
            return;
        }

        $handler = $this->handler->getClass();

        call_user_func([$handler, 'onDisconnect'], $server, $fd);
    }


    /**
     * @param Server $server
     * @param int $fd
     * @return void
     */
    public function onClose(Server $server, int $fd): void
    {
        // TODO: Implement onDisconnect() method.
        if (!$this->handler->implement(OnCloseInterface::class)) {
            return;
        }

        $handler = $this->handler->getClass();

        call_user_func([$handler, 'onClose'], $server, $fd);
    }

}
