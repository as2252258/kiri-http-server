<?php

namespace Server\Abstracts;

use Annotation\Annotation;
use Annotation\Inject;
use Http\Handler\Router;
use Kiri\Abstracts\Config;
use Kiri\Di\NoteManager;
use Kiri\Exception\ConfigException;
use Kiri\Kiri;

class WorkerStart
{


    /**
     * @var Annotation
     */
    #[Inject(Annotation::class)]
    public Annotation $annotation;


    /**
     * @var Router
     */
    #[Inject(Router::class)]
    public Router $router;


    /**
     * @param $prefix
     * @throws ConfigException
     */
    protected function setProcessName($prefix)
    {
        if (Kiri::getPlatform()->isMac()) {
            return;
        }
        $name = Config::get('id', 'system-service');
        if (!empty($prefix)) {
            $name .= '.' . $prefix;
        }
        swoole_set_process_name($name);
    }

}
