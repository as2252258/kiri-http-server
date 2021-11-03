<?php
declare(strict_types=1);

namespace Server;


use Exception;
use Kiri\Abstracts\Providers;
use Kiri\Application;
use Kiri\Kiri;

/**
 * Class DatabasesProviders
 * @package Database
 */
class ServerProviders extends Providers
{


	/**
	 * @param Application $application
	 * @throws Exception
	 */
	public function onImport(Application $application)
	{
		$application->set('server', ['class' => Server::class]);

		$container = Kiri::getDi();

		$console = $container->get(\Symfony\Component\Console\Application::class);
		$console->add($container->get(ServerCommand::class));

	}
}
