<?php
declare(strict_types=1);

namespace Kiri\Server;

use Exception;
use Kiri\Di\Context;
use Kiri\Server\Abstracts\BaseProcess;
use Swoole\Coroutine;
use Swoole\Event;
use Swoole\Process;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class HotReload extends BaseProcess
{

    /**
     * @var array|mixed
     */
    private array $watchFiles = [];


    private array $md5Map = [];

    /**
     * @var array|string[]
     */
    private array $dirs = [APP_PATH . 'app', APP_PATH . 'routes'];


    /**
     * @return string
     */
    public function getName(): string
    {
        return 'hot.load'; // TODO: Change the autogenerated stub
    }


    /**
     * @return $this
     */
    public function onSigterm(): static
    {
        // TODO: Implement onSigterm() method.
        if (Context::inCoroutine()) {
            Coroutine::create(fn() => $this->onShutdown(Coroutine::waitSignal(SIGTERM | SIGINT)));
        } else {
            Process::signal(SIGTERM | SIGINT, fn($data) => $this->onShutdown($data));
        }
        return $this;
    }


    /**
     * @param Process|null $process
     * @return void
     * @throws Exception
     */
    public function process(?Process $process): void
    {
        // TODO: Implement process() method.
        if (extension_loaded('inotify')) {
            $this->onInotifyReload();
        } else {
            $this->onCrontabReload();
        }
    }


    /**
     * @return void
     * @throws Exception
     */
    private function onCrontabReload(): void
    {
        $this->loadDirs();
        $this->tick();
    }


    /**
     * @return void
     * @throws Exception
     */
    private function onInotifyReload(): void
    {
        $init = inotify_init();
        foreach ($this->dirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $this->watch($init, rtrim($dir,'/'));
        }
        Event::add($init, fn() => $this->check($init));
        Event::cycle(function () use ($init) {
            $pid = (int)file_get_contents(storage('.swoole.pid'));
            if ($pid <= 0 || !Process::kill($pid, 0) || $this->isStop()) {
                Event::del($init);
            }
        }, true);
        Event::wait();
    }


    /**
     * @param bool $isReload
     * @throws Exception
     */
    private function loadDirs(bool $isReload = false): void
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
     * @throws Exception
     */
    public function tick(): void
    {
        $isReloading = Context::get('isReloading', false);
        if ($isReloading) {
            return;
        }

        $pid = (int)file_get_contents(storage('.swoole.pid'));
        if ($pid <= 0 || !Process::kill($pid, 0) || $this->isStop()) {
            return;
        }

        $this->loadDirs(true);

        sleep(2);

        $this->tick();
    }


    /**
     * @param $path
     * @param bool $isReload
     * @return void
     * @throws Exception
     */
    private function loadByDir($path, bool $isReload = false): void
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
                return true;
            }
            $this->md5Map[$md5] = $mTime;
        } else {
            if ($this->md5Map[$md5] != $mTime) {
                if ($isReload) {
                    return true;
                }
                $this->md5Map[$md5] = $mTime;
            }
        }
        return false;
    }


    /**
     * 开始监听
     */
    public function check($inotify): void
    {
        if (!($events = inotify_read($inotify))) {
            return;
        }
        if (Context::exists('isReloading')) {
            return;
        }

        $eventList = [IN_CREATE, IN_DELETE, IN_MODIFY, IN_MOVED_TO, IN_MOVED_FROM];
        foreach ($events as $ev) {
            if (empty($ev['name'])) {
                continue;
            }
            if ($ev['mask'] == IN_IGNORED) {
                continue;
            }
            if (!in_array($ev['mask'], $eventList)) {
                continue;
            }
            $fileType = strstr($ev['name'], '.');
            //非重启类型
            if ($fileType !== '.php') {
                continue;
            }
            if (Context::exists('swoole_timer_after')) {
                return;
            }
            $int = @swoole_timer_after(2000, fn() => $this->reload($inotify));
            Context::set('swoole_timer_after', $int);
            Context::set('isReloading', true);
        }
    }

    /**
     * @throws Exception
     */
    public function reload($inotify): void
    {
        $this->trigger_reload();

        $this->clearWatch($inotify);
        foreach ($this->dirs as $root) {
            $this->watch($inotify, $root);
        }
        Context::remove('swoole_timer_after');
        Context::remove('isReloading');
        $this->md5Map = [];
    }

    /**
     * @throws Exception
     */
    public function timerReload(): void
    {
        Context::set('isReloading', true);
        $this->trigger_reload();

        Context::set('swoole_timer_after', -1);

        $this->loadDirs();

        Context::set('isReloading', false);

        $this->tick();
    }


    /**
     * 重启
     * @throws Exception
     */
    public function trigger_reload(): void
    {
        echo 'tigger server Reload' . PHP_EOL;
        di(ServerInterface::class)->reload(false);
    }


    /**
     * @throws Exception
     */
    public function clearWatch($inotify): void
    {
        foreach ($this->watchFiles as $wd) {
            try {
                inotify_rm_watch($inotify, $wd);
            } catch (\Throwable $exception) {
                logger()->addError($exception, 'throwable');
            }
        }
        $this->watchFiles = [];
    }


    /**
     * @param $inotify
     * @param $dir
     * @return bool
     * @throws Exception
     */
    public function watch($inotify, $dir): bool
    {
        //目录不存在
        if (!is_dir($dir)) {
            return logger()->addError("[$dir] is not a directory.");
        }
        //避免重复监听
        if (isset($this->watchFiles[$dir])) {
            return FALSE;
        }

        if (in_array($dir, [APP_PATH . 'commands', APP_PATH . '.git', APP_PATH . '.gitee'])) {
            return FALSE;
        }

        $wd = @inotify_add_watch($inotify, $dir, IN_MODIFY | IN_DELETE | IN_CREATE | IN_MOVE);
        $this->watchFiles[$dir] = $wd;

        $files = scandir($dir);
        foreach ($files as $f) {
            if ($f == '.' or $f == '..' or $f == 'runtime' or preg_match('/\.txt/', $f) or preg_match('/\.sql/', $f) or preg_match('/\.log/', $f)) {
                continue;
            }
            $path = $dir . '/' . $f;
            //递归目录
            if (is_dir($path)) {
                $this->watch($inotify, $path);
                continue;
            }

            //检测文件类型
            if (strstr($f, '.') == '.php') {
                $wd = @inotify_add_watch($inotify, $path, IN_MODIFY | IN_DELETE | IN_CREATE | IN_MOVE);
                $this->watchFiles[$path] = $wd;
            }
        }
        return TRUE;
    }

}
