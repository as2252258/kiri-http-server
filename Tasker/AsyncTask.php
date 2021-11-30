<?php

namespace Server\Tasker;

use Annotation\Inject;
use Exception;
use Kiri\Di\Container;
use Psr\Container\ContainerInterface;
use Server\Contract\OnTaskInterface;
use Server\SwooleServerInterface;

class AsyncTask
{


	/**
	 * @var SwooleServerInterface
	 */
	#[Inject(SwooleServerInterface::class)]
	public SwooleServerInterface $server;


	/**
	 * @var Container
	 */
	#[Inject(ContainerInterface::class)]
	public ContainerInterface $container;


	/**
	 * @param OnTaskInterface|string $handler
	 * @param array $params
	 * @param int|null $workerId
	 * @throws Exception
	 */
	public function execute(OnTaskInterface|string $handler, array $params = [], int $workerId = null)
	{
		if ($workerId === null || $workerId <= $this->server->setting['worker_num']) {
			$workerId = random_int($this->server->setting['worker_num'] + 1,
				$this->server->setting['worker_num'] + 1 + $this->server->setting['task_worker_num']);
		}
		if (is_string($handler)) {
			$handler = $this->handle($handler, $params);
		}
		$this->server->task(serialize($handler), $workerId);
	}


	/**
	 * @param $handler
	 * @param $params
	 * @return object
	 * @throws \ReflectionException
	 * @throws Exception
	 */
	private function handle($handler, $params): object
	{
		$implements = $this->container->getReflect($handler);
		if (!in_array(OnTaskInterface::class, $implements->getInterfaceNames())) {
			throw new Exception('Task must instance ' . OnTaskInterface::class);
		}
		return $implements->newInstanceArgs($params);
	}


}
