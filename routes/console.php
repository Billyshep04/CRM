<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('invoices:generate-recurring')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('opportunities:send-follow-up-reminders')
    ->everyFiveMinutes()
    ->timezone('Europe/London')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('follow-ups:process')
    ->everyFifteenMinutes()
    ->timezone('Europe/London')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('websites:monitor --type=http')->everyTenMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('websites:monitor --type=full')->everyFourHours()->withoutOverlapping()->onOneServer();
