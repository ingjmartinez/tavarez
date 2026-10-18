<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use RuntimeException;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        $this->abortIfNotUsingDisposableDatabase($app);

        return $app;
    }

    /**
     * Los tests usan RefreshDatabase (migrate:fresh): nunca deben correr contra una base real.
     */
    private function abortIfNotUsingDisposableDatabase(Application $app): void
    {
        $connection = $app['config']->get('database.default');
        $config = $app['config']->get("database.connections.{$connection}");

        if (($config['driver'] ?? null) !== 'sqlite' || ($config['database'] ?? null) !== ':memory:') {
            throw new RuntimeException(
                "Tests abortados: la conexion '{$connection}' no es SQLite en memoria. Revisa phpunit.xml para no borrar la base de datos real."
            );
        }
    }
}
