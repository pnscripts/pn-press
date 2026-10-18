<?php

namespace App\Console\Commands;

use App\Press\Updates\UpdateChecker;
use Illuminate\Console\Command;

class PressCheckUpdates extends Command
{
    protected $signature = 'press:check-updates';

    protected $description = 'Check PN Scripts Press, plugins and themes for newer versions (notices only, nothing is installed)';

    public function handle(UpdateChecker $updates): int
    {
        if (! $updates->enabled()) {
            $this->line('Update checks are off (PRESS_UPDATE_CHECKS=false).');

            return self::SUCCESS;
        }

        $found = $updates->check();

        if ($found === []) {
            $this->info('Everything is up to date.');

            return self::SUCCESS;
        }

        $this->table(['Name', 'Installed', 'Available', 'Details'], array_map(
            fn (array $item) => [$item['name'], $item['installed'], $item['version'], $item['url'] ?? ''],
            array_values($found),
        ));

        return self::SUCCESS;
    }
}
