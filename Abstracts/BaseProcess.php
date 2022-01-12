<?php

namespace Kiri\Server\Abstracts;


use Kiri\Context;
use Kiri\Server\Broadcast\OnBroadcastInterface;
use Kiri\Server\Contract\OnProcessInterface;
use Swoole\Coroutine;
use Swoole\Process;

/**
 *
 */
abstract class BaseProcess implements OnProcessInterface
{

	protected bool $isStop = false;


	protected bool $redirect_stdin_and_stdout = FALSE;


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
	 * @return bool
	 */
	public function getRedirectStdinAndStdout(): bool
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
	 *
	 */
	public function onSigterm(): static
	{
		if (!Context::inCoroutine()) {
			Process::signal(SIGTERM, fn($data) => $this->onShutdown($data));
		} else {
			Coroutine::create(function () {
				$data = Coroutine::waitSignal(SIGTERM, -1);
				if ($data) {
					$this->onShutdown($data);
				}
			});
		}
		return $this;
	}


	/**
	 * @param $data
	 */
	protected function onShutdown($data): void
	{
		$this->isStop = true;
		$value = Context::getContext('waite:process:message');
		if (Coroutine::exists($value)) {
			Coroutine::cancel($value);
		}
	}


}
