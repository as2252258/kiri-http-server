<?php

namespace Server\Events;

class OnBeforeWorkerStart
{

    public function __construct(public int $workerId)
    {
    }

}
