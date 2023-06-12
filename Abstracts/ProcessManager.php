<?php

namespace Kiri\Server\Abstracts;

use Closure;
use Exception;
use Kiri;
use Kiri\Abstracts\Component;
use Kiri\Server\Contract\OnProcessInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;
use Swoole\Coroutine;
use Swoole\Process;
use Psr\Container\ContainerInterface;
use Kiri\Events\EventProvider;
use Kiri\Server\ServerInterface;
use Kiri\Di\Inject\Container;
use Kiri\Server\Events\OnServerBeforeStart;

class ProcessManager extends Component
{


	/** @var array<string, BaseProcess> */
	private array $_process = [];


	/**
	 * @return void
	 * @throws Exception
	 */
	public function init(): void
	{
		$provider = Kiri::getDi()->get(EventProvider::class);
		$provider->on(OnServerBeforeStart::class, [$this, 'OnServerBeforeStart']);
	}


    /**
     * @param OnServerBeforeStart $beforeStart
     * @return void
     * @throws ReflectionException
     */
	public function OnServerBeforeStart(OnServerBeforeStart $beforeStart): void
	{
		$server = Kiri::getDi()->get(ServerInterface::class);
		foreach ($this->_process as $custom) {
			if (Kiri\Di\Context::inCoroutine()) {
				Coroutine::create(function () use ($custom) {
					$custom->onSigterm()->process(null);
				});
			} else {
				$server->addProcess(new Process(function (Process $process) use ($custom) {
					$this->extracted($custom, $process);
				},
					$custom->getRedirectStdinAndStdout(),
					$custom->getPipeType(),
					$custom->isEnableCoroutine()
				));
			}
		}
	}


	/**
	 * @return Process[]
	 */
	public function getProcesses(): array
	{
		return $this->_process;
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

		$this->_process[$custom->getName()] = $custom;
	}


	/**
	 * @return void
	 */
	public function shutdown(): void
	{
//		foreach ($this->_process as $process) {
//			Process::kill($process->pid, 0) && Process::kill($process->pid, 15);
//		}
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
//		$process = $this->_process[$tag] ?? null;
//		if (empty($process)) {
//			return null;
//		}
//		if (!empty($name)) {
//			if (!isset($process[$name])) {
//				return null;
//			}
//			return $process[$name];
//		}
		return null;
	}


	/**
	 * @return void
	 */
	public function stop(): void
	{
//		foreach ($this->_process as $process) {
//			Process::kill($process->pid, 0) && Process::kill($process->pid, 15);
//		}
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
//		if (!isset($this->_process[$name])) {
//			return;
//		}
//		$process = $this->_process[$name];
//		$process->write($message);
	}

	/**
	 * @param mixed $custom
	 * @param Process $process
	 * @return void
	 * @throws Kiri\Exception\ConfigException
	 * @throws ReflectionException
	 */
	public function extracted(mixed $custom, Process $process): void
	{
		set_env('environmental', Kiri::PROCESS);
		$system = sprintf('[%s].Custom Process', \config('id', 'system-service'));
		Kiri::getLogger()->alert($system . ' ' . $custom->getName() . ' start.');
		if (Kiri::getPlatform()->isLinux()) {
			$process->name($system . '[' . $process->pid . '].' . $custom->getName());
		}
		$custom->onSigterm()->process($process);
	}


}
