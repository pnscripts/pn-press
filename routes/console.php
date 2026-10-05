<?php

use Database\Seeders\DemoCoverGenerator;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('pn-press:demo-covers', function () {
    $files = (new DemoCoverGenerator)->writeAll();

    $this->info('Wrote '.count($files).' demo covers to public/'.DemoCoverGenerator::DIRECTORY.'.');
})->purpose('Regenerate the abstract demo cover images used by the demo seeder');
