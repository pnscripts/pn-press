<?php

namespace App\Console\Commands;

use App\Press\Packages\InvalidPackage;
use App\Press\Themes\ThemeManager;
use Illuminate\Console\Command;

class PressTheme extends Command
{
    protected $signature = 'press:theme
        {action : list, install, activate or delete}
        {target? : theme slug, or for install a .zip file or a folder}
        {--replace : with install, replace an installed theme (update)}';

    protected $description = 'Manage PN Scripts Press themes';

    public function handle(ThemeManager $themes): int
    {
        $target = (string) $this->argument('target');

        try {
            switch ($this->argument('action')) {
                case 'list':
                    $active = $themes->activeSlug();
                    $this->table(['Slug', 'Name', 'Version', 'Status'], collect($themes->all())->map(fn ($t) => [
                        $t->slug, $t->name, $t->version, $t->slug === $active ? 'active' : '',
                    ])->values()->all());
                    foreach ($themes->errors() as $folder => $error) {
                        $this->warn("themes/{$folder}: {$error}");
                    }

                    return self::SUCCESS;
                case 'install':
                    $theme = is_dir($target)
                        ? $themes->installDirectory($target, (bool) $this->option('replace'))
                        : $themes->installZip($target, (bool) $this->option('replace'));
                    $this->info("Installed {$theme->name} {$theme->version}.");

                    return self::SUCCESS;
                case 'activate':
                    $themes->activate($target);
                    $this->info("{$target} is now the active theme.");

                    return self::SUCCESS;
                case 'delete':
                    $themes->uninstall($target);
                    $this->info("Deleted {$target}.");

                    return self::SUCCESS;
            }
        } catch (InvalidPackage $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->error('Unknown action. Use list, install, activate or delete.');

        return self::INVALID;
    }
}
