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

            $this->registerComposerScript();
        }
    }

    /**
     * Aggiunge automaticamente lo script "deploy" al composer.json del progetto
     * se non e' gia' presente. Eseguito solo al primo boot dopo l'installazione.
     */
    protected function registerComposerScript(): void
    {
        $composerPath = base_path('composer.json');

        if (! file_exists($composerPath)) {
            return;
        }

        $composer = json_decode(file_get_contents($composerPath), true);

        if (isset($composer['scripts']['deploy'])) {
            return;
        }

        $composer['scripts']['deploy'] = 'php artisan build:deploy';

        file_put_contents(
            $composerPath,
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL
        );
    }
}
