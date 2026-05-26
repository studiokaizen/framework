<?php

declare(strict_types=1);

namespace Zen\Database;

use Zen\Application;
use Zen\DependencyInjection\ServiceProviderInterface;

/**
 * Registers the database Connection, Migration runner, and Seeder under the
 * 'db', 'migration', and 'seeder' container keys.
 */
class DatabaseServiceProvider implements ServiceProviderInterface
{
    /**
     * Binds the database services into the container.
     *
     * @param  Application $app
     *
     * @return void
     */
    public function register(Application $app): void
    {
        $app['db'] = function (Application $app): Connection {
            $connection = $app->config('database.default', 'sqlite');

            return new Connection(
                $app->config("database.{$connection}", []),
            );
        };

        $app['migration'] = function (Application $app): Migration {
            return new Migration(
                $app['db'],
                $app->databasePath('migrations'),
            );
        };

        $app['seeder'] = function (Application $app): Seeder {
            return new Seeder(
                $app['db'],
                $app->databasePath('seeders'),
            );
        };
    }
}
