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


}
