<?php

namespace App\Console\Commands;

use App\Press\Cache\PageCache;
use Illuminate\Console\Command;

class PressPageCache extends Command
{
    protected $signature = 'press:page-cache {action=status : status or clear}';

    protected $description = 'Show or clear the PN Scripts Press page cache';

    public function handle(PageCache $cache): int
    {
        if ($this->argument('action') === 'clear') {
            $cache->flush();
            $this->info('Page cache cleared.');

            return self::SUCCESS;
        }

        if ($this->argument('action') !== 'status') {
            $this->error('Use "status" or "clear".');

            return self::INVALID;
        }

        $next = $cache->nextScheduledAt();
        $this->line('Page cache: '.($cache->enabled() ? 'on' : 'off (PRESS_PAGE_CACHE=false or APP_DEBUG=true)'));
        $this->line('Entries live for at most '.$cache->ttl().' s'.($next ? ', until the next scheduled item goes live at '.$next->toDateTimeString() : '').'.');

        return self::SUCCESS;
    }
}
