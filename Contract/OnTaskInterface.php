<?php


namespace Server\Contract;


use Swoole\Server;

interface OnTaskInterface
{

	public function execute();


	public function finish(Server $server, int $task_id);

}
