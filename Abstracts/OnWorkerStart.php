<?php

namespace Server\Abstracts;

use Annotation\Annotation;
use Annotation\Inject;
use Exception;
use Http\Handler\Router;
use Kiri\Abstracts\Config;
use Kiri\Di\NoteManager;
use Kiri\Exception\ConfigException;
use Kiri\Kiri;
use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionException;
use Server\ServerManager;

class OnWorkerStart extends WorkerStart implements EventDispatcherInterface
{


    /**
     * @param object $event
     * @return void
     * @throws Exception
     */
    public function dispatch(object $event)
    {
        ServerManager::setEnv('environmental', Kiri::WORKER);
    }

}
