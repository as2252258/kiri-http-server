<?php

namespace Kiri\Server\Abstracts;

use Kiri\Di\Context;

class DoWhile
{

	private bool $isStop = false;


	/**
	 * @param array|\Closure $handler
	 * @return void
	 */
	public static function waite(array|\Closure $handler): void
	{
		if (Context::hasContext('stop')) {
			return;
		}
		$handler();
		self::waite($handler);
	}


	/**
	 * @return void
	 */
	public static function stop(): void
	{
		Context::setContext('stop', 1);
	}


}
