<?php

namespace Server\Contract;

use Swoole\Server;


/**
 *
 */
interface OnCloseInterface
{


	/**
	 * @param Server $server
	 * @param int $fd
	 */
	public function onClose(Server $server, int $fd): void;


}
