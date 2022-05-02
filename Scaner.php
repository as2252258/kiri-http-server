<?php

namespace Kiri\Server;

use Exception;
use Kiri\Abstracts\Config;
use Kiri\Annotation\Inject;
use Kiri\Error\StdoutLoggerInterface;
use Kiri\Server\Abstracts\BaseProcess;
use Kiri\Server\SwooleServerInterface;
use Psr\Log\LoggerInterface;
use Swoole\Process;

class Scaner extends BaseProcess
{

    private array $md5Map = [];

    public bool $isReloading = FALSE;


    private array $dirs = [];


    /**
     * @var LoggerInterface
     */
    #[Inject(LoggerInterface::class)]
    public LoggerInterface $logger;


    /**
     * @throws Exception
     */
    public function process(Process $process): void
    {
        $this->dirs = Config::get('reload.inotify', []);

        $this->loadDirs();
        $this->tick();
    }


    /**
     * @param bool $isReload
     * @throws Exception
     */
    private function loadDirs(bool $isReload = FALSE)
    {
        foreach ($this->dirs as $value) {
            if (is_bool($path = realpath($value))) {
                continue;
            }

            if (!is_dir($path)) continue;

            $this->loadByDir($path, $isReload);
        }
    }


    /**
     * @param $path
     * @param bool $isReload
     * @return void
     * @throws Exception
     */
    private function loadByDir($path, bool $isReload = FALSE): void
    {
        if (!is_string($path)) {
            return;
        }
        $path = rtrim($path, '/');
        foreach (glob(realpath($path) . '/*') as $value) {
            if (is_dir($value)) {
                $this->loadByDir($value, $isReload);
            }
            if (is_file($value)) {
                if ($this->checkFile($value, $isReload)) {
                    if ($this->isReloading) {
                        break;
                    }
                    $this->isReloading = TRUE;

                    sleep(2);

                    $this->timerReload();
                    break;
                }
            }
        }
    }


    /**
     * @param $value
     * @param $isReload
     * @return bool
     */
    private function checkFile($value, $isReload): bool
    {
        $md5 = md5($value);
        $mTime = filectime($value);
        if (!isset($this->md5Map[$md5])) {
            if ($isReload) {
                return TRUE;
            }
            $this->md5Map[$md5] = $mTime;
        } else {
            if ($this->md5Map[$md5] != $mTime) {
                if ($isReload) {
                    return TRUE;
                }
                $this->md5Map[$md5] = $mTime;
            }
        }
        return FALSE;
    }


    /**
     * @throws Exception
     */
    public function timerReload()
    {
        $this->isReloading = TRUE;

        $this->logger->warning('file change');

        $swow = \Kiri::getDi()->get(SwooleServerInterface::class);

        $swow->reload();

        $this->loadDirs();

        $this->isReloading = FALSE;

        $this->tick();
    }


    /**
     * @return $this
     */
    public function onSigterm(): static
    {
        pcntl_signal(SIGTERM, function () {
            $this->onProcessStop();
        });
        return $this;
    }


    /**
     * @throws Exception
     */
    public function tick()
    {
        if ($this->isStop) {
            return;
        }

        $this->loadDirs(TRUE);

        sleep(2);

        $this->tick();
    }

}
