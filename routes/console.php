<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use App\Mail\AdminNotificationDigest;
use App\Models\Setting;
use App\Modules\Core\Services\NotificationDigestService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('pharmanp:send-digest {--to=}', function (NotificationDigestService $digestService) {
    $recipient = $this->option('to')
        ?: Setting::getValue('notification_email')
        ?: Setting::getValue('mail_from_address');

    if (! $recipient) {
        $this->error('No recipient configured. Set notification_email or pass --to=email@example.com.');
        return 1;
    }

    Mail::to($recipient)->send(new AdminNotificationDigest($digestService->adminDigest()));
    $this->info('Notification digest sent to '.$recipient.'.');

    return 0;
})->purpose('Send the admin stock, expiry and transaction digest email');
