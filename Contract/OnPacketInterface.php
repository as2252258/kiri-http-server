<?php

namespace Kiri\Server\Contract;

use Swoole\Server;

interface OnPacketInterface
{


	/**
	 * @param Server $server
	 * @param string $data
	 * @param array $clientInfo
	 * @return void
	 */
	public function onPacket(Server $server, string $data, array $clientInfo): void;

}
