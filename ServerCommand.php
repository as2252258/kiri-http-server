<?php
declare(strict_types=1);

namespace Server;


use Annotation\Inject;
use Exception;
use Http\Constrict\Request;
use Http\Constrict\RequestInterface;
use Http\Constrict\Response;
use Http\Constrict\ResponseInterface;
use Kiri\Abstracts\Config;
use Kiri\Di\ContainerInterface;
use Kiri\Events\EventProvider;
use Kiri\Exception\ConfigException;
use Kiri\Kiri;
use Server\Abstracts\OnTaskerStart as TaskerDispatch;
use Server\Abstracts\OnWorkerStart as WorkerDispatch;
use Server\Events\OnBeforeWorkerStart;
use Server\Events\OnTaskerStart;
use Server\Events\OnWorkerStart;
use Server\Handler\OnServerWorker;
use Swoole\Coroutine;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Command
 * @package Http
 */
class ServerCommand extends Command
{


	const ACTIONS = ['start', 'stop', 'restart'];


	/**
	 * @var EventProvider
	 */
	#[Inject(EventProvider::class)]
	public EventProvider $eventProvider;


	/**
	 *
	 */
	protected function configure()
	{
		$this->setName('sw:server')
			->setDescription('server start|stop|reload|restart')
			->addArgument('action', InputArgument::REQUIRED)
			->addOption('daemon', 'd', InputOption::VALUE_OPTIONAL,'is run daemonize',-1);
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int
	 * @throws Exception
	 */
	public function execute(InputInterface $input, OutputInterface $output): int
	{
		try {
			$manager = Kiri::app()->getServer();
			$manager->setDaemon((int)is_null($input->getOption('daemon')));
			if (!in_array($input->getArgument('action'), self::ACTIONS)) {
				throw new Exception('I don\'t know what I want to do.');
			}
			if ($manager->isRunner() && $input->getArgument('action') == 'start') {
				throw new Exception('Service is running. Please use restart.');
			}
			$manager->shutdown();
			if ($input->getArgument('action') == 'stop') {
				throw new Exception('shutdown success');
			}
			$this->generate_runtime_builder($manager);
		} catch (\Throwable $throwable) {
			$output->write(jTraceEx($throwable));
		} finally {
			return 1;
		}
	}


	/**
	 * @throws ConfigException
	 */
	private function configure_set()
	{
		$enable_coroutine = Config::get('servers.settings.enable_coroutine', false);
		if ($enable_coroutine != true) {
			return;
		}
		Coroutine::set([
			'hook_flags'            => SWOOLE_HOOK_ALL ^ SWOOLE_HOOK_BLOCKING_FUNCTION,
			'enable_deadlock_check' => FALSE,
			'exit_condition'        => function () {
				return Coroutine::stats()['coroutine_num'] === 0;
			}
		]);
	}


	/**
	 * @param $manager
	 * @throws ConfigException
	 */
	private function generate_runtime_builder($manager): void
	{
		$this->configure_set();

//		exec(PHP_BINARY . ' ' . APP_PATH . 'kiri.php runtime:builder');

		$this->eventProvider->on(OnBeforeWorkerStart::class, [di(OnServerWorker::class), 'setConfigure']);
		$this->eventProvider->on(OnWorkerStart::class, [di(WorkerDispatch::class), 'dispatch']);
		$this->eventProvider->on(OnTaskerStart::class, [di(TaskerDispatch::class), 'dispatch']);
		$manager->start();
	}

}
