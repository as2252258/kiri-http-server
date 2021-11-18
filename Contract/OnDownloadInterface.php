<?php

namespace Server\Contract;

use Swoole\Http\Response;

interface OnDownloadInterface
{

	public function dispatch(Response $response);

}
