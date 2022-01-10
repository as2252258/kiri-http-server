<?php

namespace Kiri\Server\Contract;

use Swoole\Http\Request;

interface OnOpenInterface
{


	/**
	 * @param Request $request
	 */
    public function onOpen(Request $request): void;

}
