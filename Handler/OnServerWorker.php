<?php

namespace Kiri\Server\Handler;

use Exception;
use Kiri;
use Kiri\Abstracts\Config;
use Kiri\Annotation\Inject;
use Kiri\Core\Help;
use Kiri\Events\EventDispatch;
use Kiri\Message\Handler\Router;
use Kiri\Server\Events\OnAfterWorkerStart;
use Kiri\Server\Events\OnBeforeWorkerStart;
use Kiri\Server\Events\OnTaskerStart as OnTaskStart;
use Kiri\Server\Events\OnWorkerError;
use Kiri\Server\Events\OnWorkerExit;
use Kiri\Server\Events\OnWorkerStart;
use Kiri\Server\Events\OnWorkerStop;
use Kiri\Message\Waite;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Swoole\Server;
use Swoole\Timer;


/**
 * Class OnServerWorker
 * @package Server\Worker
 */
class OnServerWorker extends \Kiri\Server\Abstracts\Server
{


    public Router $router;


    /**
     * @return void
     */
    public function init()
    {
		$this->router = Kiri::getDi()->get(Router::class);
    }


    /**
     * @param Server $server
     * @param int $workerId
     * @return void
     * @throws Kiri\Exception\ConfigException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Exception
     */
    public function onWorkerStart(Server $server, int $workerId)
    {
        $dispatch = \Kiri::getDi()->get(EventDispatch::class);
        $dispatch->dispatch(new OnBeforeWorkerStart($workerId));
        set_env('environmental_workerId', $workerId);
        if ($workerId < $server->setting['worker_num']) {
            set_env('environmental', Kiri::WORKER);
            $this->setProcessName(sprintf('Worker Process[%d].%d', $server->worker_pid, $workerId));
            $dispatch->dispatch(new OnWorkerStart($server, $workerId));

            $this->router->scan_build_route();
        } else {
            set_env('environmental', Kiri::TASK);
            $this->setProcessName(sprintf('Tasker Process[%d].%d', $server->worker_pid, $workerId));
            $dispatch->dispatch(new OnTaskStart($server, $workerId));
        }
        $dispatch->dispatch(new OnAfterWorkerStart());
    }


    /**
     * @param Server $server
     * @param int $workerId
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function onWorkerStop(Server $server, int $workerId)
    {
        Timer::clearAll();
        \Kiri::getDi()->get(EventDispatch::class)->dispatch(new OnWorkerStop($server, $workerId));
    }


    /**
     * @param Server $server
     * @param int $workerId
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function onWorkerExit(Server $server, int $workerId)
    {
        \Kiri::getDi()->get(EventDispatch::class)->dispatch(new OnWorkerExit($server, $workerId));
    }


    /**
     * @param Server $server
     * @param int $worker_id
     * @param int $worker_pid
     * @param int $exit_code
     * @param int $signal
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Exception
     */
    public function onWorkerError(Server $server, int $worker_id, int $worker_pid, int $exit_code, int $signal)
    {
        \Kiri::getDi()->get(EventDispatch::class)->dispatch(new OnWorkerError($server, $worker_id, $worker_pid, $exit_code, $signal));

        $message = sprintf('Worker#%d::%d error stop. signal %d, exit_code %d, msg %s',
            $worker_id, $worker_pid, $signal, $exit_code, swoole_strerror(swoole_last_error(), 9)
        );

        $this->logger->error($message);

        $this->system_mail($message);
    }


    /**
     * @param $messageContent
     * @throws Exception
     */
    protected function system_mail($messageContent)
    {
        try {
            $email = Config::get('email', ['enable' => false]);
            if (!empty($email) && ($email['enable'] ?? false) == true) {
                Help::sendEmail($email, 'Service Error', $messageContent);
            }
        } catch (\Throwable $e) {
            error($e, 'email');
        }
    }

}
