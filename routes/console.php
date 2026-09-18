<?php

use App\Support\NotificationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('notifications:subscriptions', function () {
    $summary = NotificationService::processSubscriptionReminders();

    $this->info('Subscription notification job completed.');
    $this->line('expiring_3_days: ' . $summary['expiring_3_days']);
    $this->line('expires_today: ' . $summary['expires_today']);
    $this->line('expired: ' . $summary['expired']);
})->purpose('Create reminder notifications for expiring and expired subscriptions');

Schedule::command('notifications:subscriptions')->dailyAt('09:00');
