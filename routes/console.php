<?php

use Database\Seeders\DemoCoverGenerator;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('pn-press:demo-covers', function () {
    $files = (new DemoCoverGenerator)->writeAll();

    $this->info('Wrote '.count($files).' demo covers to public/'.DemoCoverGenerator::DIRECTORY.'.');
})->purpose('Regenerate the abstract demo cover images used by the demo seeder');

// Update notices once a day (needs the scheduler: * * * * * php artisan schedule:run).
Schedule::command('press:check-updates')->daily()->when(fn () => (bool) config('press.updates.enabled'));
