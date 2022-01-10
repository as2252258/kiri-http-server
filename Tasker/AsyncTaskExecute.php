<?php

namespace Kiri\Server\Tasker;

use Exception;
use Kiri\Abstracts\Component;
use Kiri\Core\HashMap;
use Kiri\Kiri;
use ReflectionException;
use Kiri\Server\Contract\OnTaskInterface;
use Kiri\Server\SwooleServerInterface;


/**
 *
 */
class AsyncTaskExecute extends Component
{


	/**
	 * @var SwooleServerInterface|null
	 */
	public ?SwooleServerInterface $server = null;


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
