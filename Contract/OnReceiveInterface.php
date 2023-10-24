<?php

namespace Kiri\Server\Contract;

use Swoole\Server;


/**
 *
 */
interface OnReceiveInterface
{


    /**
     * @param Server $server
     * @param int $fd
     * @param int $reactor_id
     * @param string $data
     * @return bool
     */
	public function onReceive(Server $server, int $fd, int $reactor_id, string $data): bool;


}
