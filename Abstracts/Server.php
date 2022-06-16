<?php


namespace Kiri\Server\Abstracts;


use Kiri\Annotation\Inject;
use Exception;
use Kiri\Abstracts\Config;
use Kiri\Exception\ConfigException;
use Kiri;
use Psr\Log\LoggerInterface;


/**
 * Class Server
 * @package Server\Abstracts
 */
abstract class Server
{


	/**
	 * @var LoggerInterface
	 */
	#[Inject(LoggerInterface::class)]
	public LoggerInterface $logger;


	/**
	 * Server constructor.
	 * @throws Exception
	 */
	public function __construct()
	{
	}

}
