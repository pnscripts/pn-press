<?php

namespace App\Console\Commands;

use App\Press\Packages\InvalidPackage;
use App\Press\Plugins\PluginManager;
use Illuminate\Console\Command;

class PressPlugin extends Command
{
    protected $signature = 'press:plugin
        {action : list, install, activate, deactivate or uninstall}
        {target? : plugin slug, or for install a .zip file or a folder}
        {--replace : with install, replace an installed plugin (update)}';

    protected $description = 'Manage PN Scripts Press plugins';

    public function handle(PluginManager $plugins): int
    {
        $target = (string) $this->argument('target');

        try {
            switch ($this->argument('action')) {
                case 'list':
                    $this->table(['Slug', 'Name', 'Version', 'Status'], collect($plugins->all())->map(fn ($p) => [
                        $p->slug, $p->name, $p->version, $plugins->isActive($p->slug) ? 'active' : 'inactive',
                    ])->values()->all());
                    foreach ($plugins->errors() as $folder => $error) {
                        $this->warn("plugins/{$folder}: {$error}");
                    }

                    return self::SUCCESS;
                case 'install':
                    $manifest = is_dir($target)
                        ? $plugins->installDirectory($target, (bool) $this->option('replace'))
                        : $plugins->installZip($target, (bool) $this->option('replace'));
                    $this->info("Installed {$manifest->name} {$manifest->version}. Activate it with: php artisan press:plugin activate {$manifest->slug}");

                    return self::SUCCESS;
                case 'activate':
                    $plugins->activate($target);
                    $this->info("Activated {$target}.");

                    return self::SUCCESS;
                case 'deactivate':
                    $plugins->deactivate($target);
                    $this->info("Deactivated {$target}.");

                    return self::SUCCESS;
                case 'uninstall':
                    if (! $this->confirm("Uninstall {$target}? Its tables, settings and folder are deleted.", ! $this->input->isInteractive())) {
                        return self::FAILURE;
                    }
                    $plugins->uninstall($target);
                    $this->info("Uninstalled {$target}.");

                    return self::SUCCESS;
            }
        } catch (InvalidPackage $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->error('Unknown action. Use list, install, activate, deactivate or uninstall.');

        return self::INVALID;
    }
}
