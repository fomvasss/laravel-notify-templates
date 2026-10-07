<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates\Tests;

use Fomvasss\NotifyTemplates\NotifyTemplatesServiceProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

final class MigrationsTest extends OrchestraTestCase
{
    private string $dir;

    protected function getPackageProviders($app): array
    {
        return [NotifyTemplatesServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/notify-templates-migrations-'.uniqid();
        File::makeDirectory($this->dir);

        $paths = ServiceProvider::pathsToPublish(NotifyTemplatesServiceProvider::class, 'notify-templates-migrations');
        foreach ($paths as $from => $to) {
            File::copy($from, $this->dir.'/'.basename($to));
        }
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    public function test_published_migrations_run_on_fresh_install(): void
    {
        $this->artisan('migrate', ['--path' => $this->dir, '--realpath' => true])->assertSuccessful();

        $this->assertTrue(Schema::hasTable('notify_templates'));
        $this->assertTrue(Schema::hasColumns('notify_logs', ['subject', 'body']));
    }

    public function test_rolling_back_last_migration_keeps_content_columns(): void
    {
        $this->artisan('migrate', ['--path' => $this->dir, '--realpath' => true, '--step' => true])->assertSuccessful();
        $this->artisan('migrate:rollback', ['--path' => $this->dir, '--realpath' => true, '--step' => 1])->assertSuccessful();

        $this->assertTrue(Schema::hasColumns('notify_logs', ['subject', 'body']));
    }
}
