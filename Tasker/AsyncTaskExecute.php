<?php

namespace Server\Tasker;

use Exception;
use Kiri\Abstracts\BaseObject;
use Kiri\Core\HashMap;
use Kiri\Di\Container;
use Kiri\Kiri;
use Note\Inject;
use Psr\Container\ContainerInterface;
use ReflectionException;
use Server\Contract\OnTaskInterface;
use Server\SwooleServerInterface;


/**
 *
 */
class AsyncTaskExecute extends BaseObject
{


	/**
	 * @var SwooleServerInterface|null
	 */
	public ?SwooleServerInterface $server = null;


	/**
	 * @var Container
	 */
	#[Inject(ContainerInterface::class)]
	public ContainerInterface $container;


	private HashMap $hashMap;


	/**
	 *
	 */
	public function init()
	{
		$this->hashMap = new HashMap();
	}


	/**
	 * @param string $key
	 * @param $handler
	 */
	public function reg(string $key, $handler)
	{
		$this->hashMap->put($key, $handler);
	}


	/**
	 * @param OnTaskInterface|string $handler
	 * @param array $params
	 * @param int|null $workerId
	 * @throws Exception
	 */
	public function execute(OnTaskInterface|string $handler, array $params = [], int $workerId = null)
	{
		if (!$this->server) {
			$this->server = Kiri::getDi()->get(SwooleServerInterface::class);
		}
		if ($workerId === null || $workerId <= $this->server->setting['worker_num']) {
			$workerNum = $this->server->setting['worker_num'];
			$taskerNum = $workerNum + $this->server->setting['task_worker_num'];
			$workerId = random_int($workerNum, $taskerNum - 1);
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
	 * @throws ReflectionException
	 * @throws Exception
	 */
	private function handle($handler, $params): object
	{
		if (!class_exists($handler) && $this->hashMap->has($handler)) {
			$handler = $this->hashMap->get($handler);
		}
		$implements = $this->container->getReflect($handler);
		if (!in_array(OnTaskInterface::class, $implements->getInterfaceNames())) {
			throw new Exception('Task must instance ' . OnTaskInterface::class);
		}
		return $implements->newInstanceArgs($params);
	}


}