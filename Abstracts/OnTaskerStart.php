<?php

namespace Server\Abstracts;

use Kiri\Exception\ConfigException;
use Kiri\Kiri;
use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionException;
use Server\ServerManager;


/**
 *
 */
class OnTaskerStart extends WorkerStart implements EventDispatcherInterface
{


    /**
     */
    public function dispatch(object $event)
    {
        ServerManager::setEnv('environmental', Kiri::TASK);
    }


}
