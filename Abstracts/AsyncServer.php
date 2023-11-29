<?php

namespace Kiri\Server\Abstracts;

use Exception;
use Kiri;
use Kiri\Exception\ConfigException;
use Kiri\Exception\NotFindClassException;
use Kiri\Server\Config as SConfig;
use Kiri\Server\Constant;
use Kiri\Server\Events\OnServerBeforeStart;
use Kiri\Server\Events\OnShutdown;
use Kiri\Server\Handler\OnServer;
use Kiri\Server\ServerInterface;
use Kiri\Server\Task\Task;
use ReflectionException;
use Swoole\Server;

/**
 *
 */
class AsyncServer implements ServerInterface
{

    use TraitServer;


    /**
     * @var Server|null
     */
    private ?Server $server = null;


    /**
     * @param array $service
     * @param int $daemon
     * @return void
     * @throws Exception
     */
    public function initCoreServers(array $service, int $daemon = 0): void
    {
        $service = $this->genConfigService($service);
        $this->createBaseServer(array_pop($service), $daemon);
        foreach ($service as $value) {
            $this->addListener($value);
        }
        foreach ($this->_process as $process) {
            $this->server->addProcess($process);
        }
        on(OnServerBeforeStart::class, [$this, 'onSignal']);
    }


    /**
     * @return bool
     * @throws ReflectionException
     */
    public function shutdown(): bool
    {
        $this->server->shutdown();

        event(new OnShutdown());

        return true;
    }


    /**
     * @param SConfig $config
     * @param int $daemon
     * @return void
     * @throws ConfigException
     * @throws NotFindClassException
     * @throws ReflectionException
     */
    private function createBaseServer(SConfig $config, int $daemon = 0): void
    {
        $match = $this->getServerClass($config->type);
        if (is_null($match)) {
            throw new NotFindClassException('Unknown server type ' . $config->type);
        }
        $this->initServer($match, $config, $daemon);
        $this->onEventListen($this->server, \config('server.events', []));
        $this->onEventListen($this->server, $config->events);
        $this->onTaskListen();
    }


    /**
     * @param $match
     * @param $config
     * @param $daemon
     * @return void
     * @throws ConfigException
     */
    private function initServer($match, $config, $daemon): void
    {
        $this->server = new $match($config->host, $config->port, $config->mode, $config->socket);
        $this->server->set($this->systemConfig($config, $daemon));
        if (!isset($config->events[Constant::SHUTDOWN])) {
            $config->events[Constant::SHUTDOWN] = [OnServer::class, 'onShutdown'];
        }
        Kiri::getDi()->bind(ServerInterface::class, $this->server);
    }


    /**
     * @return void
     * @throws
     */
    private function onTaskListen(): void
    {
        if (!isset($this->server->setting[Constant::OPTION_TASK_WORKER_NUM])) {
            return;
        }
        $container = Kiri::getDi();
        $container->get(Task::class)->initTaskWorker($this->server);
    }


    /**
     * @param SConfig $config
     * @param int $daemon
     * @return array
     * @throws Exception
     * @throws ConfigException
     */
    protected function systemConfig(SConfig $config, int $daemon): array
    {
        $settings                                     = array_merge(\config('server.settings', []), $config->settings);
        $settings[Constant::OPTION_DAEMONIZE]         = (bool)$daemon;
        $settings[Constant::OPTION_ENABLE_REUSE_PORT] = true;
        $settings[Constant::OPTION_PID_FILE]          = storage('.swoole.pid');
        if (!isset($settings[Constant::OPTION_PID_FILE])) {
            $settings[Constant::OPTION_LOG_FILE] = storage('system.log');
        }
        return $settings;
    }


    /**
     * @param SConfig $config
     * @return void
     * @throws Exception
     */
    public function addListener(SConfig $config): void
    {
        $port = $this->server->addlistener($config->host, $config->port, $config->socket);
        if ($port === false) {
            throw new Exception('Listen port fail.' . swoole_last_error());
        }
        println('Add port listen ' . $config->host . '::' . $config->port);
        $port->set($this->resetSettings($config->type, $config->settings));

        $this->onEventListen($port, $config->getEvents());
    }


    /**
     * @param string $type
     * @param array $settings
     * @return array
     * @throws
     */
    private function resetSettings(string $type, array $settings): array
    {
        if ($type == Constant::SERVER_TYPE_HTTP && !isset($settings['open_http_protocol'])) {
            $settings['open_http_protocol'] = true;
            if (in_array($this->server->setting['dispatch_mode'], [2, 4])) {
                $settings['open_http2_protocol'] = true;
            }
        }
        if ($type == Constant::SERVER_TYPE_WEBSOCKET && !isset($settings['open_websocket_protocol'])) {
            $settings['open_websocket_protocol'] = true;
        }
        return $settings;
    }


    /**
     * @param Server\Port|Server $base
     * @param array $events
     * @return void
     * @throws ReflectionException
     */
    private function onEventListen(Server\Port|Server $base, array $events): void
    {
        foreach ($events as $name => $event) {
            if (is_array($event) && is_string($event[0])) {
                $event[0] = Kiri::getDi()->get($event[0]);
            }
            $base->on($name, $event);
        }
    }


    /**
     * @return void
     * @throws
     */
    public function start(): void
    {
        event(new OnServerBeforeStart());
        $this->server->start();
    }


}
