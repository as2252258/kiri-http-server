<?php

namespace Kiri\Server\Handler;

use Exception;
use Kiri;
use Kiri\Core\Help;
use Kiri\Events\EventDispatch;
use Kiri\Server\Events\OnAfterWorkerStart;
use Kiri\Server\Events\OnBeforeWorkerStart;
use Kiri\Server\Events\OnTaskerStart as OnTaskerStart;
use Kiri\Server\Events\OnWorkerError;
use Kiri\Server\Events\OnWorkerExit;
use Kiri\Server\Events\OnWorkerStart;
use Kiri\Server\Events\OnWorkerStop;
use ReflectionException;
use Swoole\Server;
use Kiri\Di\Inject\Container;
use Swoole\Timer;


/**
 * Class OnServerWorker
 * @package Server\Worker
 */
class OnServerWorker extends \Kiri\Server\Abstracts\Server
{


    /**
     * @var EventDispatch
     */
    #[Container(EventDispatch::class)]
    public EventDispatch $dispatch;


    /**
     * @return void
     */
    public function init(): void
    {
        on(OnBeforeWorkerStart::class, [$this, 'onWorkerNameAlias']);
    }


    /**
     * @param OnBeforeWorkerStart $workerStart
     * @return void
     */
    public function onWorkerNameAlias(OnBeforeWorkerStart $workerStart): void
    {
        set_env('environmental_workerId', $workerStart->workerId);
        if ($workerStart->workerId < $workerStart->server->setting['worker_num']) {
            $this->processName($workerStart->server, 'Worker');
            set_env('environmental', Kiri::WORKER);
        } else {
            $this->processName($workerStart->server, 'Tasker');
            set_env('environmental', Kiri::TASK);
        }
    }


    /**
     * @param Server $server
     * @param int $workerId
     * @return void
     * @throws Exception
     */
    public function onWorkerStart(Server $server, int $workerId): void
    {
        $this->dispatch->dispatch(new OnBeforeWorkerStart($server, $workerId));
        if ($workerId < $server->setting['worker_num']) {
            $this->dispatch->dispatch(new OnWorkerStart($server, $workerId));
        } else {
            $this->dispatch->dispatch(new OnTaskerStart($server, $workerId));
        }
        $this->dispatch->dispatch(new OnAfterWorkerStart($server, $workerId));
    }


    /**
     * @param Server $server
     * @param string $prefix
     * @return void
     */
    protected function processName(Server $server, string $prefix): void
    {
        Kiri::setProcessName(sprintf($prefix . ' Process[%d].%d', $server->worker_pid, $server->worker_id));
    }


    /**
     * @param Server $server
     * @param int $workerId
     * @throws ReflectionException
     */
    public function onWorkerStop(Server $server, int $workerId): void
    {
        event(new OnWorkerStop($server, $workerId));
        Timer::clearAll();
    }


    /**
     * @param Server $server
     * @param int $workerId
     * @throws ReflectionException
     */
    public function onWorkerExit(Server $server, int $workerId): void
    {
        event(new OnWorkerExit($server, $workerId));
    }


    /**
     * @param Server $server
     * @param int $worker_id
     * @param int $worker_pid
     * @param int $exit_code
     * @param int $signal
     * @throws Exception
     */
    public function onWorkerError(Server $server, int $worker_id, int $worker_pid, int $exit_code, int $signal): void
    {
        event(new OnWorkerError($server, $worker_id, $worker_pid, $exit_code, $signal));

        debug_print_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);
        $message = sprintf('Worker#%d::%d error stop. signal %d, exit_code %d, msg %s', $worker_id, $worker_pid, $signal, $exit_code, swoole_strerror(swoole_last_error(), $signal));

        error($message);

        $this->system_mail($message);
    }


    /**
     * @param $messageContent
     * @throws Exception
     */
    protected function system_mail($messageContent): void
    {
        try {
            $email = \config('email', ['enable' => false]);
            if (!empty($email) && ($email['enable'] ?? false)) {
                Help::sendEmail($email, 'Service Error', $messageContent);
            }
        } catch (\Throwable $e) {
            error($e, ['email']);
        }
    }

}
