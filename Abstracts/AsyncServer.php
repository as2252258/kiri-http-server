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
use Kiri\Di\Inject\Container;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;
use Swoole\Server;
use Symfony\Component\Console\Output\OutputInterface;

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
     * @var ContainerInterface
     */
    #[Container(ContainerInterface::class)]
    public ContainerInterface $container;


    /**
     * @param array $service
     * @param int $daemon
     * @return void
     * @throws ConfigException
     * @throws ContainerExceptionInterface
     * @throws NotFindClassException
     * @throws NotFoundExceptionInterface
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
     * @throws ContainerExceptionInterface
     * @throws NotFindClassException
     * @throws NotFoundExceptionInterface
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
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function initServer($match, $config, $daemon): void
    {
        $this->server = new $match($config->host, $config->port, $config->mode, $config->socket);
        $this->server->set($this->systemConfig($config, $daemon));
        if (!isset($config->events[Constant::SHUTDOWN])) {
            $config->events[Constant::SHUTDOWN] = [OnServer::class, 'onShutdown'];
        }
        $this->_listenDump($config);
        $this->container->bind(ServerInterface::class, $this->server);
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
        $this->container->get(Task::class)->initTaskWorker($this->server);
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
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Exception
     */
    public function addListener(SConfig $config): void
    {
        $port = $this->server->addlistener($config->host, $config->port, $config->socket);
        if ($port === false) {
            throw new Exception('Listen port fail.' . swoole_last_error());
        }
        $this->_listenDump($config);
        $port->set($this->resetSettings($config->type, $config->settings));
        $this->onEventListen($port, $config->getEvents());
    }


    /**
     * @param SConfig $config
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function _listenDump(SConfig $config): void
    {
        $writeln = $this->container->get(OutputInterface::class);
        if ($config->type == Constant::SERVER_TYPE_HTTP) {
            $writeln->writeln('Add http port listen ' . $config->host . '::' . $config->port);
        } else if ($config->type == Constant::SERVER_TYPE_WEBSOCKET) {
            $writeln->writeln('Add wss  port listen ' . $config->host . '::' . $config->port);
        } else if ($config->type == Constant::SERVER_TYPE_UDP) {
            $writeln->writeln('Add udp  port listen ' . $config->host . '::' . $config->port);
        } else {
            $writeln->writeln('Add tcp  port listen ' . $config->host . '::' . $config->port);
        }
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
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function onEventListen(Server\Port|Server $base, array $events): void
    {
        foreach ($events as $name => $event) {
            if (is_array($event) && is_string($event[0])) {
                $event[0] = $this->container->get($event[0]);
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
