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
	 * @var LoggerInterface
	 */
	#[Container(LoggerInterface::class)]
	public LoggerInterface $logger;


	/**
	 * Server constructor.
	 * @throws Exception
	 */
	public function __construct()
	{
	}

}
