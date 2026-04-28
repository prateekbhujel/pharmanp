<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdminNotificationDigest extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly array $digest,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('PharmaNP Daily Notification Digest')
            ->view('emails.admin-notification-digest');
    }
}
