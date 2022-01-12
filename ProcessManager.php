<?php

namespace Kiri\Server;

use Kiri\Abstracts\Config;
use Kiri\Context;
use Kiri\Exception\ConfigException;
use Kiri\Kiri;
use Kiri\Server\Abstracts\BaseProcess;
use Kiri\Server\Broadcast\Message;
use Kiri\Server\Contract\OnProcessInterface;
use Swoole\Coroutine;
use Swoole\Process;

class ProcessManager
{


	/** @var array<string, Process> */
	private array $_process = [];


	/**
	 * @param string|OnProcessInterface|BaseProcess $customProcess
	 * @return void
	 * @throws ConfigException
	 */
	public function add(string|OnProcessInterface|BaseProcess $customProcess)
	{
		$server = Kiri::getDi()->get(SwooleServerInterface::class);
		if (is_string($customProcess)) {
			$customProcess = Kiri::getDi()->get($customProcess);
		}
		$system = sprintf('[%s].process', Config::get('id', 'system-service'));
		$server->logger->debug($system . ' ' . $customProcess->getName() . ' start.');
		$server->addProcess($process = $this->parse($customProcess, $system));
		$this->_process[$customProcess->getName()] = $process;
	}


	/**
	 * @param $customProcess
	 * @param $system
	 * @return Process
	 */
	private function parse($customProcess, $system): Process
	{
		return new Process(function (Process $process) use ($customProcess, $system) {
			if (Kiri::getPlatform()->isLinux()) {
				$process->name($system . '(' . $customProcess->getName() . ')');
			}
			$channel = Coroutine::create(function () use ($process, $customProcess) {
				while (!$customProcess->isStop()) {
					$message = $process->read();
					if (!empty($message)) {
						$message = unserialize($message);
					}
					if (is_null($message)) {
						continue;
					}
					$customProcess->onBroadcast($message);
				}
			});
			Context::setContext('waite:process:message', $channel);

			$customProcess->onSigterm()->process($process);
		},
			$customProcess->getRedirectStdinAndStdout(),
			$customProcess->getPipeType(),
			$customProcess->isEnableCoroutine()
		);
	}


	/**
	 * @param array $processes
	 * @return void
	 * @throws ConfigException
	 */
	public function batch(array $processes)
	{
		foreach ($processes as $process) {
			$this->add($process);
		}
	}


	/**
	 * @param string $message
	 * @param string $name
	 * @return void
	 */
	public function push(string $message, string $name = '')
	{
		$processes = $this->_process;
		if (!empty($this->_process[$name])) {
			$processes = [$this->_process[$name]];
		}
		foreach ($processes as $process) {
			$process->write(serialize(new Message($message)));
		}
	}


}
