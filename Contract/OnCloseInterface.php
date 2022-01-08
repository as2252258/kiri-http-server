<?php

namespace Server\Contract;

use Swoole\Server;


/**
 *
 */
interface OnCloseInterface
{


	/**
	 * @param int $fd
	 */
	public function onClose(int $fd): void;


}
