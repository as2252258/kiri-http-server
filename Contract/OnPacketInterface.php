<?php

namespace Server\Contract;

use Server\Abstracts\Server;

interface OnPacketInterface
{


	/**
	 * @param Server $server
	 * @param string $data
	 * @param array $clientInfo
	 * @return mixed
	 */
	public function onPacket(Server $server, string $data, array $clientInfo): void;

}
