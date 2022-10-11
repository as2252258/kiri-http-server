<?php

namespace Kiri\Server\Contract;

/**
 *
 */
interface OnCloseInterface
{


	/**
	 * @param int $fd
	 * @return void
	 */
	public function OnClose(int $fd): void;


}
