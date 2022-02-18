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


	/** @var array<string, Process> */
	private array $_taskProcess = [];


	#[Inject(LoggerInterface::class)]
	public LoggerInterface $logger;

	/**
	 * @param string|OnProcessInterface|BaseProcess $customProcess
	 * @param string $tag
	 * @return void
	 * @throws ConfigException
	 */
	public function add(string|OnProcessInterface|BaseProcess $customProcess, string $tag = 'default')
	{
		if (is_string($customProcess)) {
			$customProcess = Kiri::getDi()->get($customProcess);
		}

		$system = sprintf('[%s].Custom Process', Config::get('id', 'system-service'));

		$this->logger->debug($system . ' ' . $customProcess->getName() . ' start.');
		$process = $this->parse($customProcess, $system);
		if (!Kiri::getDi()->has(SwooleServerInterface::class)) {
			$process->start();
		} else {
			$server = Kiri::getDi()->get(SwooleServerInterface::class);

			$server->addProcess($process = $this->parse($customProcess, $system));
		}
		$this->_process[$tag][$customProcess->getName()] = $process;
	}


	/**
	 * @param string|null $name
	 * @param string $tag
	 * @return array|Process|null
	 */
	public function get(?string $name = null, string $tag = 'default'): array|Process|null
	{
		$process = $this->_process[$tag] ?? null;
		if (empty($process)) {
			return null;
		}
		if (!empty($name)) {
			if (!isset($process[$name])) {
				return null;
			}
			return $process[$name];
		}
		return $process;
	}


	/**
	 * @return void
	 */
	public function stop()
	{
		foreach ($this->_process as $process) {
			$process->exit(0);
		}
		foreach ($this->_taskProcess as $process) {
			$process->exit(0);
		}
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
	 * @param string $tag
	 * @return void
	 * @throws ConfigException
	 */
	public function batch(array $processes, string $tag = 'default')
	{
		foreach ($processes as $process) {
			$this->add($process, $tag);
		}
	}


	/**
	 * @param string $message
	 * @param string $name
	 * @param string $tag
	 * @return void
	 */
	public function push(string $message, string $name = '', string $tag = 'default')
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
