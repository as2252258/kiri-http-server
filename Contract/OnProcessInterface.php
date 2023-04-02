<?php


namespace Kiri\Server\Contract;


use Swoole\Process;


/**
 * Interface BaseProcess
 * @package Contract
 */
interface OnProcessInterface
{


	/**
	 * @param ?Process $process
	 */
	public function process(?Process $process): void;



}
