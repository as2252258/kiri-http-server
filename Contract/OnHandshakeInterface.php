<?php

namespace Kiri\Server\Contract;

use Swoole\Http\Request;
use Swoole\Http\Response;


/**
 *
 */
interface OnHandshakeInterface
{


	/**
	 * @param Request $request
	 * @param Response $response
	 */
	public function onHandshake(Request $request, Response $response): void;

}
