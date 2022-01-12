<?php

namespace Kiri\Server\Broadcast;


use Kiri\Server\Contract\OnPipeMessageInterface;

class Message implements OnPipeMessageInterface
{

	/**
	 * @param mixed $data
	 */
	public function __construct(public mixed $data)
	{
	}


	/**
	 * @return void
	 */
	public function process(): void
	{
		$workerId = func_get_args()[1];

		var_dump($workerId, $this->data);
	}

}
