<?php

namespace Kiri\Server\Broadcast;

interface OnBroadcastInterface
{


	/**
	 * @return void
	 */
	public function process(): void;

}
