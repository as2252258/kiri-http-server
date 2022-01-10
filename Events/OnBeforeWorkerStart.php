<?php

namespace Kiri\Server\Events;

class OnBeforeWorkerStart
{

    public function __construct(public int $workerId)
    {
    }

}
