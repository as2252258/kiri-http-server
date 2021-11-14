<?php

namespace Server\Websocket;


use Annotation\Inject;
use Exception;
use Http\Handler\Router;
use Kiri\Error\Logger;
use Kiri\Kiri;
use Server\SInterface\OnCloseInterface;
use Swoole\Coroutine\Http\Server as CoroutineServer;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\WebSocket\CloseFrame;


class Server
{

    private CoroutineServer $server;


    /**
     * @var Router
     */
    #[Inject(Router::class)]
    public Router $router;

    public function start()
    {
        $this->server->start();
    }


    /**
     * @param string $Host
     * @param int $Port
     * @param bool $isSsl
     * @param array $settings
     */
    public function initCore(string $Host, int $Port, bool $isSsl, array $settings)
    {
        $this->server = new CoroutineServer($Host, $Port, $isSsl);
        $this->server->set($settings['setting'] ?? []);

        $this->server->handle('/', function (Request $request, Response $response) {
            $class = new \stdClass();
            if ($class instanceof OnHandshakeInterface) {
                $class->onHandshake($request, $response);
            } else if ($class instanceof OnOpenInterface) {
                $response->upgrade();
                $class->onOpen($this->server, $request);
            }
            if (!($class instanceof OnMessageInterface)) {
                $response->setStatusCode(200);
                $response->end();
            } else {
                $this->recover($class, $response);
            }
        });
    }


    /**
     * @param OnCloseInterface|OnMessageInterface $class
     * @param Response $response
     * @return bool
     * @throws Exception
     */
    private function recover(OnCloseInterface|OnMessageInterface $class, Response $response): bool
    {
        $frame = $response->recv();
        if ($frame === '' || $frame === FALSE) {
            return $this->onClose($class, $response);
        }
        if ($frame->data == 'close' || get_class($frame) === CloseFrame::class) {
            return $this->onClose($class, $response);
        }
        $class->onMessage($this->server, $frame);
        return $this->recover($class, $response);
    }


    /**
     * @param OnCloseInterface|OnMessageInterface $class
     * @param Response $response
     * @return bool
     * @throws Exception
     */
    private function onClose(OnCloseInterface|OnMessageInterface $class, Response $response): bool
    {
        if (!($close = $response->close())) {
            Kiri::getDi()->get(Logger::class)->warning('close websocket fail.');
        }
        $class->onClose($this->server, $response->fd);
        return $close;
    }


    /**
     * @return CoroutineServer
     */
    public function getServer(): CoroutineServer
    {
        return $this->server;
    }

}