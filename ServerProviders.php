<?php
declare(strict_types=1);

namespace Kiri\Server;


use Kiri\Abstracts\Providers;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\Console\Application;
use Kiri;

/**
 * Class DatabasesProviders
 * @package Database
 */
class ServerProviders extends Providers
{


	/**
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function onImport(): void
	{
		$server = $this->container->get(ServerCommand::class);

		$console = $this->container->get(Application::class);
		$console->add($server);
	}
}
