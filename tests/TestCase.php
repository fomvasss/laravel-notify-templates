<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates\Tests;

use Fomvasss\NotifyTemplates\NotifyTemplatesServiceProvider;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
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
        $app['config']->set('notifytemplates.discover', []);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTables();
    }

    private function createTables(): void
    {
        Schema::create('notify_templates', function ($table) {
            $table->id();
            $table->string('notify_key', 100);
            $table->string('channel', 50)->nullable();
            $table->string('role_key', 100)->nullable();
            $table->string('tenant_id', 100)->nullable();
            $table->text('subject')->nullable();
            $table->longText('body')->nullable();
            $table->json('options')->nullable();
            $table->timestamps();
        });

        Schema::create('notify_role_subscriptions', function ($table) {
            $table->id();
            $table->string('role_key', 100);
            $table->string('notify_key', 100);
            $table->string('tenant_id', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('personal_only')->default(false);
            $table->json('channels')->nullable();
            $table->json('options')->nullable();
            $table->timestamps();
        });
    }
}
