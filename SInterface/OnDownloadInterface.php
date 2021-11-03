<?php

namespace Server\SInterface;

use Swoole\Http\Response;

interface OnDownloadInterface
{

	public function dispatch(Response $response);

}
