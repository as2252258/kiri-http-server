<?php

namespace Kiri\Server\Contract;


use Swoole\WebSocket\Server;

/**
 *
 */
interface OnCloseInterface
{


	/**
	 * @param int $fd
	 * @return void
	 */
	public function onClose(int $fd): void;


}
