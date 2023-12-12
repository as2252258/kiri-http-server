<?php
declare(strict_types=1);

namespace Kiri\Server;


use Kiri\Abstracts\Providers;
use Symfony\Component\Console\Application;

/**
 * Class DatabasesProviders
 * @package Database
 */
class ServerProviders extends Providers
{


    /**
     * @throws
     */
    public function onImport(): void
    {
        $server = $this->container->get(ServerCommand::class);

        $console = $this->container->get(Application::class);
        $console->add($server);
    }
}
