<?php

namespace DiegoCopat\LaravelDeploy;

use DiegoCopat\LaravelDeploy\Commands\BuildDeployCommand;
use Illuminate\Support\ServiceProvider;

class LaravelDeployServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                BuildDeployCommand::class,
            ]);
        }
    }
}
