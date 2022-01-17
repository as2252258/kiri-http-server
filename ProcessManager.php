<?php

namespace Kiri\Server;

use Kiri;
use Kiri\Abstracts\Config;
use Kiri\Annotation\Inject;
use Kiri\Context;
use Kiri\Events\EventDispatch;
use Kiri\Exception\ConfigException;
use Kiri\Server\Abstracts\BaseProcess;
use Kiri\Server\Broadcast\Message;
use Kiri\Server\Contract\OnProcessInterface;
use Kiri\Server\Events\OnProcessStart;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine;
use Swoole\Process;

class ProcessManager
{


	/** @var array<string, Process> */
	private array $_process = [];


	#[Inject(LoggerInterface::class)]
	public LoggerInterface $logger;

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

		$this->logger->debug($system . ' ' . $customProcess->getName() . ' start.');
		$process = $this->parse($customProcess, $system);
		if (Context::inCoroutine()) {
			Coroutine::create(function () use ($process) {
				$process->start();
			});
		} else {
			$server->addProcess($process = $this->parse($customProcess, $system));
		}
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

			Kiri::getDi()->get(EventDispatch::class)->dispatch(new OnProcessStart());

			set_env('environmental', Kiri::PROCESS);
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
