<?php

namespace Kiri\Server\Abstracts;


use Kiri\Di\Context;
use Kiri\Di\Inject\Container;
use Kiri\Error\StdoutLogger;
use Kiri\Server\Contract\OnProcessInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine;

/**
 *
 */
abstract class BaseProcess implements OnProcessInterface
{

    private bool $stop = false;


    protected bool $redirect_stdin_and_stdout = FALSE;


    protected int $pipe_type = SOCK_DGRAM;


    protected bool $enable_coroutine = false;


    /**
     * @var StdoutLogger
     */
    #[Container(LoggerInterface::class)]
    public StdoutLogger $logger;


    /**
     * @var \Kiri\Di\Container
     */
    #[Container(ContainerInterface::class)]
    public \Kiri\Di\Container $container;


    public string $name = '';


    /**
     * @return string
     */
    public function getName(): string
    {
        if (empty($this->name)) {
            $this->name = uniqid('p.');
        }
        return $this->name;
    }


    /**
     * @return bool
     */
    public function isStop(): bool
    {
        return $this->stop;
    }

    /**
     * @return bool
     */
    public function getRedirectStdinAndStdout(): bool
    {
        return $this->redirect_stdin_and_stdout;
    }

    /**
     * @return int
     */
    public function getPipeType(): int
    {
        return $this->pipe_type;
    }

    /**
     * @return bool
     */
    public function isEnableCoroutine(): bool
    {
        return $this->enable_coroutine;
    }


    /**
     *
     */
    public function stop(): void
    {
        $this->stop = true;
    }


    /**
     * @return $this
     */
    abstract public function onSigterm(): static;


    /**
     * @param $data
     * @return void
     */
    protected function onShutdown($data): void
    {
        $this->stop = true;
        $value      = Context::get('waite:process:message');
        $this->logger->alert('Process ' . $this->getName() . ' stop');
        if (!is_null($value) && Coroutine::exists((int)$value)) {
            Coroutine::cancel((int)$value);
        }
    }


}
