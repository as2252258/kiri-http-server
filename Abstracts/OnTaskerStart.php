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
     * @throws ConfigException
     */
    public function dispatch(object $event)
    {
        $time = microtime(true);

        ServerManager::setEnv('environmental', Kiri::TASK);
//        if (!is_enable_file_modification_listening()) {
//            $this->interpretDirectory();
//        }

        $this->mixed($event, false, $time);
    }


}
