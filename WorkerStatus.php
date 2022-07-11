<?php

namespace Kiri\Server;

use Kiri\Server\Abstracts\StatusEnum;

class WorkerStatus
{


	public StatusEnum $enum;


	/**
	 * @param StatusEnum $enum
	 */
	public function setEnum(StatusEnum $enum): void
	{
		$this->enum = $enum;
	}


	/**
	 * @param StatusEnum $enum
	 * @return bool
	 */
	public function is(StatusEnum $enum): bool
	{
		return $this->enum == $enum;
	}


}
