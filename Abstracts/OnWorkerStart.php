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
     * @throws ConfigException
     * @throws Exception
     */
    public function dispatch(object $event)
    {
        $time = microtime(true);

        ServerManager::setEnv('environmental', Kiri::WORKER);
//        if (is_enable_file_modification_listening()) {
//            $this->router->read_files();
//            $this->interpretDirectory();
//        }
        $this->mixed($event, true, $time);
    }

}
