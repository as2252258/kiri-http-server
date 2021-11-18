<?php

namespace Server\Abstracts;


use JetBrains\PhpStorm\Pure;
use Server\Contract\OnProcessInterface;
use Swoole\Coroutine;
use Swoole\Process;

/**
 *
 */
abstract class BaseProcess implements OnProcessInterface
{

	protected bool $isStop = false;


	protected mixed $redirect_stdin_and_stdout = null;


	protected int $pipe_type = SOCK_DGRAM;


	protected bool $enable_coroutine = true;


	public string $name = 'swoole process.';


	/**
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}


	/**
	 * @return bool
	 */
	public function isStop(): bool
	{
		return $this->isStop;
	}

	/**
	 * @return mixed
	 */
	public function getRedirectStdinAndStdout(): mixed
	{
		return $this->redirect_stdin_and_stdout;
	}

	/**
	 * @return int
	 */
	public function getPipeType(): int
	{
		return $this->pipe_type;
	}

	/**
	 * @return bool
	 */
	public function isEnableCoroutine(): bool
	{
		return $this->enable_coroutine;
	}


	/**
	 *
	 */
	public function onProcessStop(): void
	{
		$this->isStop = true;
	}


	/**
	 * @return bool
	 */
	public function checkProcessIsStop(): bool
	{
		return $this->isStop === true;
	}


	/**
	 * @param Process $process
	 */
	public function signListen(Process $process): void
	{
	}


	/**
	 *
	 */
	protected function exit(): void
	{
		putenv('process.status=idle');
	}


	/**
	 * @return bool
	 */
	#[Pure] public function isWorking(): bool
	{
		return env('process.status', 'working') == 'working';
	}


	/**
	 *
	 */
	private function waiteExit(Process $process): void
	{
		$this->onProcessStop();
		while ($this->isWorking()) {
			$this->sleep();
		}
		$process->exit(0);
	}


	/**
	 *
	 */
	private function sleep(): void
	{
		if ($this->enable_coroutine) {
			Coroutine::sleep(0.1);
		} else {
			usleep(100);
		}
	}

}
