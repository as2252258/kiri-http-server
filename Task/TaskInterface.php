<?php

namespace Kiri\Server\Task;

interface TaskInterface
{


    /**
     * @param array $tasks
     * @param float $timeout
     * @return false|array
     */
    public function taskWaitMulti(array $tasks, float $timeout = 0.5): false|array;

    /**
     * @param array $tasks
     * @param float $timeout
     * @return false|array
     */
    public function taskCo(array $tasks, float $timeout = 0.5): false|array;

    /**
     * @param mixed $data
     * @param float $timeout
     * @param int $dstWorkerId
     * @return mixed
     */
    public function taskWait(mixed $data, float $timeout = 0.5, int $dstWorkerId = -1): mixed;

}
