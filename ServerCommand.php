<?php
declare(strict_types=1);

namespace Server;


use Annotation\Inject;
use Exception;
use Kiri\Abstracts\Config;
use Kiri\Events\EventProvider;
use Kiri\Exception\ConfigException;
use Kiri\Kiri;
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
			->addArgument('action', InputArgument::OPTIONAL, 'run action', 'start')
			->addOption('daemon', 'd', InputOption::VALUE_OPTIONAL, 'is run daemonize');
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int
	 * @throws Exception
	 */
	public function execute(InputInterface $input, OutputInterface $output): int
	{
		$manager = Kiri::app()->getServer();
		$manager->setDaemon((int)!is_null($input->getOption('daemon')));
		if (is_null($input->getArgument('action'))) {
			$input->setArgument('action', 'restart');
		}
		if (!in_array($input->getArgument('action'), self::ACTIONS)) {
			throw new Exception('I don\'t know what I want to do.');
		}

		$reusePort = Config::get('server.settings')[Constant::OPTION_ENABLE_REUSE_PORT] ?? false;
		if (!$reusePort) {
			if ($manager->isRunner() && $input->getArgument('action') == 'start') {
				throw new Exception('Service is running. Please use restart.');
			}
			$manager->shutdown();
		}
		if ($input->getArgument('action') == 'stop') {
			throw new Exception('shutdown success');
		}
		return $this->generate_runtime_builder($manager);
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
	 * @return int
	 * @throws ConfigException
	 * @throws Exception
	 */
	private function generate_runtime_builder($manager): int
	{
		$this->configure_set();

		Kiri::app()->getRouter()->read_files();

		$manager->start();

		return 1;
	}

}
