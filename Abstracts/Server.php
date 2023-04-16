<?php


namespace Kiri\Server\Abstracts;


use Exception;
use Kiri;
use Psr\Log\LoggerInterface;
use Kiri\Di\Inject\Container;


/**
 * Class Server
 * @package Server\Abstracts
 */
abstract class Server
{

	/**
	 * Server constructor.
	 * @throws Exception
	 */
	public function __construct()
	{
	}

}
