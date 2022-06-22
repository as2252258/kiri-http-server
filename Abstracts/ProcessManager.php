<?php

namespace Kiri\Server\Abstracts;

use Closure;
use Kiri;
use Kiri\Abstracts\Config;
use Kiri\Context;
use Kiri\Events\EventDispatch;
use Kiri\Exception\ConfigException;
use Kiri\Server\Broadcast\Message;
use Kiri\Server\Contract\OnProcessInterface;
use Kiri\Server\Events\OnProcessStart;
use Psr\Log\LoggerInterface;
use Swoole\Process;
use Kiri\Server\Events\OnProcessStop;
use Kiri\Di\ContainerInterface;

class ProcessManager
{


	/** @var array<string, Process> */
	private array $_process = [];


	/**
	 * @param ContainerInterface $container
	 * @param LoggerInterface $logger
	 */
	public function __construct(public ContainerInterface $container, public LoggerInterface $logger)
	{
	}


	/**
	 * @param string|OnProcessInterface|BaseProcess $customProcess
	 * @return array
	 */
	public function add(string|OnProcessInterface|BaseProcess $customProcess): array
	{
		if (is_string($customProcess)) {
			$customProcess = Kiri::getDi()->get($customProcess);
		}

		if (Context::inCoroutine()) {
			return [$customProcess, $this->resolve($customProcess)];
		}

		$process = new Process($this->resolve($customProcess),
			$customProcess->getRedirectStdinAndStdout(),
			$customProcess->getPipeType(),
			$customProcess->isEnableCoroutine()
		);

		return [$customProcess, $process];
	}


	/**
	 * @return void
	 */
	public function shutdown(): void
	{
		foreach ($this->_process as $process) {
			Process::kill($process->pid, 0) && Process::kill($process->pid, 15);
		}
	}


	/**
	 * @param BaseProcess $customProcess
	 * @return Closure
	 */
	public function resolve(BaseProcess $customProcess): Closure
	{
		return static function (Process $process) use ($customProcess) {
			set_env('environmental', Kiri::PROCESS);
			$system = sprintf('[%s].Custom Process', Config::get('id', 'system-service'));
			Kiri::getLogger()->alert($system . ' ' . $customProcess->getName() . ' start.');
			if (Kiri::getPlatform()->isLinux()) {
				$process->name($system . '(' . $customProcess->getName() . ')');
			}
			$customProcess->onSigterm()->process($process);
		};
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
	public function stop(): void
	{
		foreach ($this->_process as $process) {
			Process::kill($process->pid, 0) && Process::kill($process->pid, 15);
		}
	}


	/**
	 * @param array|null $processes
	 * @param \Swoole\Server|null $server
	 * @return void
	 * @throws ConfigException
	 */
	public function batch(?array $processes, ?\Swoole\Server $server = null): void
	{
		if (empty($processes)) {
			return;
		}
		foreach ($processes as $process) {
			[$customProcess, $sProcess] = $this->add($process);

			$this->_process[$customProcess->getName()] = $customProcess;

			$server->addProcess($sProcess);
		}
	}


	/**
	 * @param string $message
	 * @param string $name
	 * @param string $tag
	 * @return void
	 */
	public function push(string $message, string $name = '', string $tag = 'default'): void
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
