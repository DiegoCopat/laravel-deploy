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

    protected $description = 'Pulisce build, esegue npm build, verifica configurazione server e push sul branch Git corrente';

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

        // 2. Verifica/crea file di configurazione server
        $this->ensureServerConfig();

        // 3. Elimina la cartella build
        $this->deleteBuildFolder();

        // 4. Esegui npm run build
        if (!$this->option('skip-build')) {
            $this->runNpmBuild();
        } else {
            $this->warn('Build npm saltato (--skip-build).');
        }

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

    /**
     * Verifica che index.php e .htaccess esistano nella root del progetto.
     * Se non esistono, li crea con la configurazione corretta per servire
     * tutto dalla cartella public/ senza dover copiare file fuori.
     */
    private function ensureServerConfig(): void
    {
        $this->comment('Verifica configurazione server...');

        // index.php nella root
        $indexPath = base_path('index.php');
        if (!File::exists($indexPath)) {
            File::put($indexPath, '<?php' . PHP_EOL . PHP_EOL . 'require __DIR__."/public/index.php";' . PHP_EOL);
            $this->info('  + index.php creato');
        } else {
            $this->line('  - index.php presente');
        }

        // .htaccess nella root
        $htaccessPath = base_path('.htaccess');
        if (!File::exists($htaccessPath)) {
            $htaccessContent = <<<'HTACCESS'
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Serve static files from public/ if they exist there
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{DOCUMENT_ROOT}/public/%{REQUEST_URI} -f
    RewriteRule ^(.*)$ public/$1 [L]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ public/index.php [L]
</IfModule>
HTACCESS;

            File::put($htaccessPath, $htaccessContent);
            $this->info('  + .htaccess creato');
        } else {
            $this->line('  - .htaccess presente');
        }
    }

    private function deleteBuildFolder(): void
    {
        $this->comment('Pulizia cartella build...');

        $publicBuildPath = public_path('build');

        if (File::exists($publicBuildPath)) {
            File::deleteDirectory($publicBuildPath);
            $this->line('  - public/build/ eliminata');
        }

        // Rimuovi la vecchia cartella build dalla root se esiste (legacy)
        $legacyBuildPath = base_path('build');
        if (File::exists($legacyBuildPath)) {
            File::deleteDirectory($legacyBuildPath);
            $this->line('  - build/ (legacy) eliminata');
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
