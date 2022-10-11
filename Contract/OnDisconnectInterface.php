<?php

namespace Kiri\Server\Contract;

interface OnDisconnectInterface
{


	/**
	 * @param int $fd
	 */
    public function OnDisconnect(int $fd): void;


}
