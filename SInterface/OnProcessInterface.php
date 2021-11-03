<?php


namespace Server\SInterface;


use Swoole\Process;


/**
 * Interface BaseProcess
 * @package SInterface
 */
interface OnProcessInterface
{


	/**
	 * @param Process $process
	 * @return string
	 */
	public function getProcessName(Process $process): string;


	/**
	 * @param Process $process
	 */
	public function signListen(Process $process): void;


	/**
	 * @param Process $process
	 */
	public function onHandler(Process $process): void;


	/**
	 *
	 */
	public function onProcessStop(): void;


}
