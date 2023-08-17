<?php

namespace Kiri\Server\Task;

use Kiri\Di\Inject\Container;
use Kiri\Server\ServerInterface;

class TaskExecute implements TaskInterface
{

    /**
     * @var ServerInterface
     */
    #[Container(ServerInterface::class)]
    public ServerInterface $server;




    /**
     * @param mixed $data
     * @param float $timeout
     * @param int $dstWorkerId
     * @return mixed
     */
    public function taskWait(mixed $data, float $timeout = 0.5, int $dstWorkerId = -1): mixed
    {
        return $this->server->taskwait($data, $timeout, $dstWorkerId);
    }


    /**
     * @param array $tasks
     * @param float $timeout
     * @return false|array
     */
    public function taskCo(array $tasks, float $timeout = 0.5): false|array
    {
        return $this->server->taskCo($tasks, $timeout);
    }


    /**
     * @param array $tasks
     * @param float $timeout
     * @return false|array
     */
    public function taskWaitMulti(array $tasks, float $timeout = 0.5): false|array
    {
        return $this->server->taskWaitMulti($tasks, $timeout);
    }


}