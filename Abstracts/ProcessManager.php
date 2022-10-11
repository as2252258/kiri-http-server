<?php

namespace Kiri\Server\Abstracts;

use Closure;
use Exception;
use Kiri;
use Kiri\Abstracts\Config;
use Kiri\Abstracts\Component;
use Kiri\Server\Contract\OnProcessInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Swoole\Process;
use Kiri\Annotation\Inject;
use Kiri\Di\ContainerInterface;
use Kiri\Events\EventProvider;
use Kiri\Server\ServerInterface;
use Kiri\Server\Events\OnServerBeforeStart;

class ProcessManager extends Component
{


	/** @var array<string, Process> */
	private array $_process = [];


	/**
	 * @var ContainerInterface
	 */
	#[Inject(ContainerInterface::class)]
	public ContainerInterface $container;


	#[Inject(EventProvider::class)]
	public EventProvider $provider;

	/**
	 * @return void
	 * @throws Exception
	 */
	public function init(): void
	{
		$this->provider->on(OnServerBeforeStart::class, [$this, 'OnServerBeforeStart']);
	}


	/**
	 * @param OnServerBeforeStart $beforeStart
	 * @return void
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function OnServerBeforeStart(OnServerBeforeStart $beforeStart): void
	{
		$server = $this->container->get(ServerInterface::class);
		foreach ($this->_process as $process) {
			$server->addProcess($process);
		}
	}


	/**
	 * @param string|OnProcessInterface|BaseProcess $custom
	 * @throws Exception
	 */
	public function add(string|OnProcessInterface|BaseProcess $custom): void
	{
		if (is_string($custom)) {
			$custom = Kiri::getDi()->get($custom);
		}
		if (isset($this->_process[$custom->getName()])) {
			throw new Exception('Process(' . $custom->getName() . ') is exists.');
		}
		$this->_process[$custom->getName()] = new Process(function (Process $process) use ($custom) {
			$this->extracted($custom, $process);
		},
			$custom->getRedirectStdinAndStdout(),
			$custom->getPipeType(),
			$custom->isEnableCoroutine()
		);
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
			$this->extracted($customProcess, $process);
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
	 * @return void
	 * @throws Exception
	 */
	public function batch(?array $processes): void
	{
		if (empty($processes)) {
			return;
		}
		foreach ($processes as $process) {
			$this->add($process);
		}
	}


	/**
	 * @param string $message
	 * @param string $name
	 * @return void
	 */
	public function push(string $name, string $message): void
	{
		if (!isset($this->_process[$name])) {
			return;
		}
		$process = $this->_process[$name];
		$process->write($message);
	}
	
	/**
	 * @param mixed $custom
	 * @param Process $process
	 * @return void
	 * @throws Kiri\Exception\ConfigException
	 */
	public function extracted(mixed $custom, Process $process): void
	{
		set_env('environmental', Kiri::PROCESS);
		$system = sprintf('[%s].Custom Process', Config::get('id', 'system-service'));
		Kiri::getLogger()->alert($system . ' ' . $custom->getName() . ' start.');
		if (Kiri::getPlatform()->isLinux()) {
			$process->name($system . '[' . $process->pid . '].' . $custom->getName());
		}
		$custom->onSigterm()->process($process);
	}
	
	
}
