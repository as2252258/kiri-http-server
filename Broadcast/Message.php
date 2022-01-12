<?php

namespace Kiri\Server\Broadcast;


use Kiri\Kiri;
use Kiri\Server\Contract\OnPipeMessageInterface;
use Psr\Log\LoggerInterface;

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
		$logger = Kiri::getDi()->get(LoggerInterface::class);
		$logger->debug('workerId::' . env('environmental_workerId'));
		$logger->debug($this->data . '::' . static::class);
	}

}
