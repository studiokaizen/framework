<?php

declare(strict_types=1);

namespace Zen\Console;

use Zen\Application;
use Zen\Console\Commands\CacheClearCommand;
use Zen\Console\Commands\DbSeedCommand;
use Zen\Console\Commands\MakeMigrationCommand;
use Zen\Console\Commands\MakeSeederCommand;
use Zen\Console\Commands\MigrateCommand;
use Zen\Console\Commands\MigrateFreshCommand;
use Zen\Console\Commands\MigrateResetCommand;
use Zen\Console\Commands\MigrateRollbackCommand;
use Zen\Console\Commands\MigrateStatusCommand;
use Zen\Console\Commands\QueueWorkCommand;
use Zen\Console\Commands\KeyGenerateCommand;
use Zen\Console\Commands\RouteListCommand;
use Zen\Console\Commands\ScheduleRunCommand;
use Zen\DependencyInjection\ServiceProviderInterface;

/**
 * Registers the Console instance and all built-in commands in the container.
 */
class ConsoleServiceProvider implements ServiceProviderInterface
{
    /**
     * Binds the 'console' service and wires up every built-in command.
     *
     * @param  Application $app
     *
     * @return void
     */
    public function register(Application $app): void
    {
        $app['console'] = function (Application $app): Console {
            $console = new Console();

            $console->add(
                new KeyGenerateCommand(),
                new CacheClearCommand($app['cache']),
                new DbSeedCommand($app['seeder']),
                new MakeMigrationCommand($app->databasePath('migrations')),
                new MakeSeederCommand($app->databasePath('seeders')),
                new MigrateCommand($app['migration']),
                new MigrateRollbackCommand($app['migration']),
                new MigrateResetCommand($app['migration']),
                new MigrateFreshCommand($app['migration'], $app['seeder']),
                new MigrateStatusCommand($app['migration']),
                new QueueWorkCommand($app['worker']),
                new RouteListCommand($app['router']),
                new ScheduleRunCommand($app['scheduler']),
            );

            return $console;
        };
    }
}
