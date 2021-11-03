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
     * @throws \ReflectionException
     * @throws \Exception
     */
    protected function interpretDirectory()
    {
        $di = Kiri::getDi();

        $this->annotation->read(APP_PATH . 'app', 'App');

        $fileLists = $this->annotation->read(APP_PATH . 'app');
        foreach ($fileLists->runtime(APP_PATH . 'app') as $class) {
            foreach (NoteManager::getTargetNote($class) as $value) {
                $value->execute($class);
            }
            $methods = $di->getMethodAttribute($class);
            foreach ($methods as $method => $attribute) {
                if (empty($attribute)) {
                    continue;
                }
                foreach ($attribute as $item) {
                    $item->execute($class, $method);
                }
            }
        }
    }


    /**
     * @param $event
     * @param $isWorker
     * @param $time
     * @throws ConfigException
     */
    protected function mixed($event, $isWorker, $time)
    {
        $name = Config::get('id', 'system-service');
        echo sprintf("\033[36m[" . date('Y-m-d H:i:s') . "]\033[0m [%s]Builder %s[%d].%d use time %s.", $name, $isWorker ? 'Worker' : 'Taker',
                $event->server->worker_pid, $event->workerId, round(microtime(true) - $time, 6) . 's') . PHP_EOL;
    }

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
