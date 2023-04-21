<?php

namespace Kiri\Server\Task;

interface TaskInterface
{


	/**
	 * @param array $handler
	 * @param int|null $workerId
	 * @return void
	 */
	public function dispatch(array $handler, ?int $workerId = null): void;


}
