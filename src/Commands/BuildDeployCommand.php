<?php

namespace DiegoCopat\LaravelDeploy\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class BuildDeployCommand extends Command
{
    protected $signature = 'build:deploy
        {message? : Messaggio di commit}
        {--skip-build : Salta npm run build}
        {--skip-push : Esegui solo build e commit senza push}';

    protected $description = 'Pulisce build, esegue npm build, commit e push sul branch Git corrente';

    public function handle(): int
    {
        $this->info('');
        $this->info('========================================');
        $this->info('   Build & Deploy - by Diego Copat');
        $this->info('========================================');
        $this->info('');

        // 1. Rileva branch corrente
        $branch = $this->getCurrentBranch();
        $this->info("Branch corrente: <fg=green>{$branch}</>");

        // 2. Elimina le cartelle build
        $this->deleteBuildFolders();

        // 3. Esegui npm run build
        if (!$this->option('skip-build')) {
            $this->runNpmBuild();
        } else {
            $this->warn('Build npm saltato (--skip-build).');
        }

        // 4. Copia la cartella build nella directory principale
        $this->copyBuildFolder();

        // 5. Git add, commit e push
        $this->gitDeploy($branch);

        $this->info('');
        $this->info('Deploy completato con successo!');
        $this->info('');

        return Command::SUCCESS;
    }

    private function getCurrentBranch(): string
    {
        $process = new Process(['git', 'rev-parse', '--abbrev-ref', 'HEAD']);
        $process->setWorkingDirectory(base_path());
        $process->mustRun();

        return trim($process->getOutput());
    }

    private function deleteBuildFolders(): void
    {
        $this->comment('Pulizia cartelle build...');

        $mainBuildPath = base_path('build');
        $publicBuildPath = public_path('build');

        if (File::exists($mainBuildPath)) {
            File::deleteDirectory($mainBuildPath);
            $this->line('  - build/ eliminata');
        }

        if (File::exists($publicBuildPath)) {
            File::deleteDirectory($publicBuildPath);
            $this->line('  - public/build/ eliminata');
        }
    }

    private function runNpmBuild(): void
    {
        $this->comment('Esecuzione npm run build...');

        $process = new Process(['npm', 'run', 'build']);
        $process->setWorkingDirectory(base_path());
        $process->setTimeout(300);

        try {
            $process->mustRun();
            $this->info('  Build completato.');
        } catch (ProcessFailedException $exception) {
            $this->error('Errore durante npm run build:');
            $this->error($exception->getMessage());
            exit(1);
        }
    }

    private function copyBuildFolder(): void
    {
        $this->comment('Copia build nella directory principale...');

        $sourcePath = public_path('build');
        $destinationPath = base_path('build');

        if (!File::exists($sourcePath)) {
            $this->error('La cartella public/build non esiste.');
            exit(1);
        }

        File::copyDirectory($sourcePath, $destinationPath);
        $this->info('  Build copiato.');
    }

    private function gitDeploy(string $branch): void
    {
        $this->comment('Git deploy...');

        // Git add
        $this->runProcess(['git', 'add', '-A'], 'git add');

        // Controlla se ci sono modifiche
        $statusProcess = new Process(['git', 'status', '--porcelain']);
        $statusProcess->setWorkingDirectory(base_path());
        $statusProcess->mustRun();

        if (empty(trim($statusProcess->getOutput()))) {
            $this->warn('  Nessuna modifica da committare.');
            return;
        }

        // Commit
        $message = $this->argument('message')
            ?? 'build: deploy ' . now()->format('Y-m-d H:i');
        $this->runProcess(['git', 'commit', '-m', $message], 'git commit');

        // Push
        if (!$this->option('skip-push')) {
            $this->runProcess(['git', 'push', 'origin', $branch], "git push origin {$branch}");
            $this->info("  Push completato su <fg=green>{$branch}</>.");
        } else {
            $this->warn('  Push saltato (--skip-push).');
        }
    }

    private function runProcess(array $command, string $label): void
    {
        $process = new Process($command);
        $process->setWorkingDirectory(base_path());

        try {
            $process->mustRun();
        } catch (ProcessFailedException $exception) {
            $this->error("Errore durante {$label}:");
            $this->error($exception->getMessage());
            exit(1);
        }
    }
}
