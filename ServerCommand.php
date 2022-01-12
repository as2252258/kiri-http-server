<?php
declare(strict_types=1);

namespace Kiri\Server;


use Exception;
use Kiri\Abstracts\Config;
use Kiri\Events\EventDispatch;
use Kiri\Exception\ConfigException;
use Kiri;
use Kiri\Annotation\Inject;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
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
	 * @var EventDispatch
	 */
	#[Inject(EventDispatch::class)]
	public EventDispatch $eventProvider;


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
	 * @throws ConfigException
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 * @throws \ReflectionException
	 * @throws Exception
	 */
	public function execute(InputInterface $input, OutputInterface $output): int
	{
		$manager = Kiri::app()->getServer();
		$manager->setDaemon((int)!is_null($input->getOption('daemon')));

        $action = $input->getArgument('action');
        if (is_null($action)) {
			throw new Exception('I don\'t know what I want to do.');
		}
		if (!in_array($action, self::ACTIONS)) {
			throw new Exception('I don\'t know what I want to do.');
		}
		if ($action == 'restart' || $action == 'stop') {
			$manager->shutdown();
            if ($action == 'stop') {
                return 1;
            }
		}
        return (int)$manager->start();
	}

}
