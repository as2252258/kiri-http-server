<?php

namespace Kiri\Server\Events;

class OnAfterWorkerStart
{


    /**
     * @param int $workerId
     */
    public function __construct(public int $workerId)
    {
    }

}
