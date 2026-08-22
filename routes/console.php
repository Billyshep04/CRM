<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\ProcessWebsiteProvisioning;
use App\Models\WebsiteProvisioningRun;

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

Artisan::command('websites:resume-provisioning', function (): void {
    WebsiteProvisioningRun::query()
        ->whereIn('state', ['waiting_for_dns', 'waiting_for_ssl'])
        ->where('next_check_at', '<=', now())
        ->each(fn (WebsiteProvisioningRun $run) => ProcessWebsiteProvisioning::dispatch($run->id));
})->purpose('Resume website provisioning runs waiting for DNS or SSL.');

Schedule::command('websites:resume-provisioning')->everyTenMinutes()->withoutOverlapping()->onOneServer();
