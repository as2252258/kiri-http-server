<?php
declare(strict_types=1);

namespace Kiri\Server;


use Exception;
use Kiri;
use Kiri\Events\EventDispatch;
use Kiri\Router\Router;
use Kiri\Server\Abstracts\AsyncServer;
use Kiri\Server\Events\OnShutdown;
use ReflectionException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

defined('ROUTER_TYPE_HTTP') or define('ROUTER_TYPE_HTTP', 'http');
defined('PID_PATH') or define('PID_PATH', APP_PATH . 'storage/server.pid');

/**
 * Class Command
 * @package Http
 */
class ServerCommand extends Command
{


    public AsyncServer   $manager;
    public State         $state;
    public EventDispatch $dispatch;
    public Router        $router;


    /**
     * @param string|null $name
     * @throws ReflectionException
     */
    public function __construct(string $name = null)
    {
        parent::__construct($name);
        $container = Kiri::getDi();
        $this->manager = $container->get(AsyncServer::class);
        $this->state = $container->get(State::class);
        $this->dispatch = $container->get(EventDispatch::class);
        $this->router = $container->get(Router::class);
    }


    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('sw:server')
            ->setDescription('server start|stop|reload|restart')
            ->addArgument('action', InputArgument::OPTIONAL, 'run action', 'start')
            ->addOption('daemon', 'd', InputOption::VALUE_NONE, 'is run daemonize');
    }


    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws Exception
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        return match ($input->getArgument('action')) {
            'restart' => $this->restart($input),
            'stop'    => $this->stop(),
            'start'   => $this->start($input),
            default   =>
            throw new Exception('I don\'t know what I want to do.')
        };
    }


    /**
     * @param InputInterface $input
     * @return int
     * @throws ReflectionException
     */
    protected function restart(InputInterface $input): int
    {
        $this->stop();
        $this->start($input);
        return 1;
    }


    /**
     * @return int
     * @throws ReflectionException
     * @throws Exception
     */
    protected function stop(): int
    {
        $configs = \config('server', []);
        $instances = $this->manager->sortService($configs['ports'] ?? []);
        foreach ($instances as $config) {
            $this->state->exit($config->port);
        }
        $this->dispatch->dispatch(new OnShutdown());
        return 1;
    }


    /**
     * @param InputInterface $input
     * @return int
     * @throws
     */
    protected function start(InputInterface $input): int
    {
        $daemon = (int)$input->getOption('daemon');
        if (\config('reload.hot', false) === true) {
            $this->manager->addProcess(HotReload::class);
        } else {
            $this->router->scan_build_route();
        }
        $this->manager->initCoreServers(\config('server', []), $daemon);
        $this->manager->start();
        return 1;
    }

}
