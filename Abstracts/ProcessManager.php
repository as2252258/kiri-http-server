<?php

namespace Kiri\Server;

use Closure;
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
use Kiri\Server\Events\OnProcessStop;
use Kiri\Di\ContainerInterface;
use Swoole\Timer;

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
	 * @throws ConfigException
	 */
	public function add(string|OnProcessInterface|BaseProcess $customProcess): array
	{
		if (is_string($customProcess)) {
			$customProcess = Kiri::getDi()->get($customProcess);
		}

		$system = sprintf('[%s].Custom Process', Config::get('id', 'system-service'));

		$this->logger->debug($system . ' ' . $customProcess->getName() . ' start.');
		if (Context::inCoroutine()) {
			return [$customProcess, $this->resolve($customProcess, $system)];
		}

		$process = new Process($this->resolve($customProcess, $system),
			$customProcess->getRedirectStdinAndStdout(),
			$customProcess->getPipeType(),
			$customProcess->isEnableCoroutine()
		);

		return [$customProcess, $process];
	}


	/**
	 * @param $customProcess
	 * @param $system
	 * @return Closure
	 */
	public function resolve($customProcess, $system): Closure
	{
		return static function () use ($customProcess, $system) {
			$process = func_get_arg(0);
			if ($process instanceof Process\Pool) {
				$process = $process->getProcess(func_get_arg(1));
			}
			set_env('environmental', Kiri::PROCESS);
			if (Kiri::getPlatform()->isLinux()) {
				$process->name($system . '(' . $customProcess->getName() . ')');
			}
			$dispatcher = Kiri::getDi()->get(EventDispatch::class);
			$dispatcher->dispatch(new OnProcessStart());
			$customProcess->onSigterm()->process($process);
			$dispatcher->dispatch(new OnProcessStop($process));
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
			$process->exit(0);
		}
	}


	/**
	 * @param array $processes
	 * @param \Swoole\Server|null $server
	 * @return void
	 * @throws ConfigException
	 */
	public function batch(array $processes, ?\Swoole\Server $server = null): void
	{
		$processes = array_merge($processes, Config::get('processes', []));

		if (Context::inCoroutine()) {
			$this->poolManager($processes);
			return;
		}

		foreach ($processes as $process) {
			[$customProcess, $sProcess] = $this->add($process);

			$this->_process[$customProcess->getName()] = $customProcess;

			$server->addProcess($sProcess);
		}
	}


	/**
	 * @param array $processes
	 * @return void
	 * @throws ConfigException
	 */
	protected function poolManager(array $processes): void
	{
		$manager = new Process\Manager();
		foreach ($processes as $process) {
			/** @var BaseProcess $customProcess */
			[$customProcess, $sProcess] = $this->add($process);

			$this->_process[$customProcess->getName()] = $customProcess;

			$manager->add($sProcess, $customProcess->isEnableCoroutine());
		}
		$manager->start();
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
