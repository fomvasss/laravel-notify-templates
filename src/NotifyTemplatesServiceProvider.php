<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates;

use Fomvasss\NotifyTemplates\Console\MakeNotifyCommand;
use Illuminate\Support\ServiceProvider;

class NotifyTemplatesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/notify-templates.php', 'notify-templates');

        $this->app->singleton(NotifyTemplatesManager::class, fn() => new NotifyTemplatesManager());
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/notify-templates.php' => config_path('notify-templates.php'),
        ], 'notify-templates-config');

        $this->commands([MakeNotifyCommand::class]);

        $this->publishMigrations();

        $manager = $this->app->make(NotifyTemplatesManager::class);

        foreach (config('notify-templates.discover', []) as $path) {
            $manager->discoverIn($path);
        }

        $configTypes = config('notify-templates.types', []);
        if ($configTypes) {
            $manager->registerTypes($configTypes);
        }
    }

    private function publishMigrations(): void
    {
        $migration = 'create_notifytemplates_tables';

        if (!glob(database_path("migrations/*_{$migration}.php"))) {
            $this->publishes([
                __DIR__."/../database/migrations/{$migration}.php.stub" => database_path(
                    'migrations/'.date('Y_m_d_His')."_{$migration}.php"
                ),
            ], 'notify-templates-migrations');
        }
    }
}
