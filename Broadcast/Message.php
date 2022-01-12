<?php

namespace Kiri\Server\Broadcast;


use Kiri;
use Kiri\Server\Contract\OnPipeMessageInterface;
use Psr\Log\LoggerInterface;


/**
 *
 */
class Message implements OnPipeMessageInterface, OnBroadcastInterface
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
		$logger = Kiri::getDi()->get(LoggerInterface::class);
		$logger->debug(env('environmental') . '::' . env('environmental_workerId', 0) . '::' . $this->data);
	}

}
