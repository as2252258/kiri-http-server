<?php


namespace Server\Contract;


use Swoole\Process;


/**
 * Interface BaseProcess
 * @package Contract
 */
interface OnProcessInterface
{


	/**
	 * @param Process $process
	 */
	public function onProcessExec(Process $process): void;


	/**
	 *
	 */
	public function onProcessStop(): void;


}
