<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates\Console;

use Illuminate\Console\GeneratorCommand;

class MakeNotifyCommand extends GeneratorCommand
{
    protected $name = 'notify:make';

    protected $description = 'Create a new Notify class';

    protected $type = 'Notify';

    protected function getStub(): string
    {
        return file_exists($custom = $this->laravel->basePath('stubs/notify.stub'))
            ? $custom
            : __DIR__.'/stubs/notify.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Notifications';
    }

    protected function qualifyClass($name): string
    {
        $name = ltrim($name, '\\/');

        if (!str_ends_with($name, 'Notify')) {
            $name .= 'Notify';
        }

        return parent::qualifyClass($name);
    }

    protected function replaceClass($stub, $name): string
    {
        $stub = parent::replaceClass($stub, $name);

        $notifyKey = str_replace('Notify', '', class_basename($name));

        return str_replace('{{ notifyKey }}', $notifyKey, $stub);
    }
}
