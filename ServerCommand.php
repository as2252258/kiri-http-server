<?php
declare(strict_types=1);

namespace Kiri\Server;


use Exception;
use Kiri;
use Kiri\Abstracts\Config;
use Kiri\Exception\ConfigException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;
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


	private Server $server;


	/**
	 * @return void
	 */
	protected function configure(): void
	{
		$this->server = Kiri::getDi()->get(Server::class);
		$this->setName('sw:server')
			->setDescription('server start|stop|reload|restart')
			->addArgument('action', InputArgument::OPTIONAL, 'run action', 'start')
			->addOption('daemon', 'd', InputOption::VALUE_OPTIONAL, 'is run daemonize');
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int
	 * @throws ConfigException
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 * @throws Exception
	 */
	public function execute(InputInterface $input, OutputInterface $output): int
	{
		return match ($input->getArgument('action')) {
			'restart' => $this->restart($input),
			'stop' => $this->stop(),
			'start' => $this->start($input),
			default =>
			throw new Exception('I don\'t know what I want to do.')
		};
	}


	/**
	 * @param InputInterface $input
	 * @return int
	 * @throws ConfigException
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	protected function restart(InputInterface $input): int
	{
		$this->stop();
		$this->start($input);
		return 1;
	}


	/**
	 * @return int
	 * @throws ConfigException
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	protected function stop(): int
	{
		$this->server->shutdown();
		return 1;
	}


	/**
	 * @param InputInterface $input
	 * @return int
	 * @throws ConfigException
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	protected function start(InputInterface $input): int
	{
		$this->server->setDaemon((int)!is_null($input->getOption('daemon')));
		$this->server->start();
		return 1;
	}

}
