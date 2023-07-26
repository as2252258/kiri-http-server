<?php

namespace Kiri\Server\Abstracts;

use Exception;
use Kiri;
use ReflectionException;
use Swoole\Coroutine;
use Swoole\Http\Server as HServer;
use Swoole\Process;
use Swoole\Server;
use Kiri\Server\Constant;
use Kiri\Server\Config;
use Swoole\WebSocket\Server as WServer;

trait TraitServer
{


    private array $_process = [];


    /**
     * @param string|array|BaseProcess $class
     * @return void
     * @throws Exception
     */
    public function addProcess(string|array|BaseProcess $class): void
    {
        if (!is_array($class)) {
            $class = [$class];
        }
        foreach ($class as $name) {
            if (is_string($name)) {
                $name = Kiri::getDi()->get($name);
            }
            if (isset($this->_process[$name->getName()])) {
                throw new Exception('Process(' . $name->getName() . ') is exists.');
            }
            $this->_process[$name->getName()] = $this->genProcess($name);
        }
    }


    /**
     * @param BaseProcess $name
     * @return Process
     */
    private function genProcess(BaseProcess $name): Process
    {
        return new Process(function (Process $process) use ($name) {
            $process->name($name->getName());
            $name->onSigterm()->process($process);
        },
            $name->getRedirectStdinAndStdout(),
            $name->getPipeType(),
            $name->isEnableCoroutine());
    }


    /**
     * @return void
     * @throws Exception
     */
    public function onSignal(): void
    {
        $signal = \config('signal', []);
        $this->onPcntlSignal(SIGINT, [$this, 'onSigint']);
        foreach ($signal as $sig => $value) {
            if (is_array($value) && is_string($value[0])) {
                $value[0] = \Kiri::getDi()->get($value[0]);
            }
            if (!is_callable($value, true)) {
                throw new Exception('Register signal callback must can exec.');
            }
            $this->onPcntlSignal($sig, $value);
        }
    }


    /**
     * @param $signal
     * @param $callback
     * @return void
     */
    private function onPcntlSignal($signal, $callback): void
    {
        pcntl_signal($signal, $callback);
    }


    /**
     * @return array
     */
    public function getProcess(): array
    {
        return $this->_process;
    }


    /**
     * @param array $ports
     * @return array
     */
    public function sortService(array $ports): array
    {
        $array = [];
        foreach ($ports as $port) {
            $array = $this->sort($array, $port);
        }
        return $array;
    }


    /**
     * @param array $ports
     * @return array<Config>
     */
    public function genConfigService(array $ports): array
    {
        $array = [];
        $ports = $ports['ports'] ?? [];
        foreach ($ports as $port) {
            $array = $this->sort($array, $port);
        }
        return $array;
    }


    /**
     * @param array $array
     * @param $port
     * @return array
     */
    private function sort(array $array, $port): array
    {
        $config = instance(Config::class, [], $port);
        if ($port['type'] == Constant::SERVER_TYPE_WEBSOCKET) {
            array_unshift($array, $config);
        } else if ($port['type'] == Constant::SERVER_TYPE_HTTP) {
            if (!empty($array) && $array[0]['type'] == Constant::SERVER_TYPE_WEBSOCKET) {
                $array[] = $config;
            } else {
                array_unshift($array, $config);
            }
        } else {
            $array[] = $config;
        }
        return $array;
    }


    /**
     * @param $type
     * @return string|null
     */
    public function getServerClass($type): ?string
    {
        return match ($type) {
            Constant::SERVER_TYPE_BASE, Constant::SERVER_TYPE_TCP,
            Constant::SERVER_TYPE_UDP       => Server::class,
            Constant::SERVER_TYPE_HTTP      => HServer::class,
            Constant::SERVER_TYPE_WEBSOCKET => WServer::class,
            default                         => null
        };
    }


    /**
     * @param $type
     * @return string|null
     */
    public function getCoroutineServerClass($type): ?string
    {
        return match ($type) {
            Constant::SERVER_TYPE_BASE, Constant::SERVER_TYPE_TCP, Constant::SERVER_TYPE_UDP => Coroutine\Server::class,
            Constant::SERVER_TYPE_HTTP, Constant::SERVER_TYPE_WEBSOCKET                      => Coroutine\Http\Server::class,
            default                                                                          => null
        };
    }


}
