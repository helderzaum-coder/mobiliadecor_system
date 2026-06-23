<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('contas:processar-recorrentes')->daily()->withoutOverlapping();
Schedule::command('bling:importar-automatico')->everyThreeHours()->withoutOverlapping();
Schedule::command('ml:reprocessar-dados primary --limit=20')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('ml:reprocessar-dados secondary --limit=20')->everyFiveMinutes()->withoutOverlapping();
