<?php

namespace App\Modules\Setup\Services;

use App\Core\Services\DocumentNumberService;
use App\Modules\Setup\Repositories\Interfaces\SettingsRepositoryInterface;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class SettingsAdminService
{
    public function __construct(private readonly SettingsRepositoryInterface $settings) {}

    public function settings(): array
    {
        return [
            'company_email' => $this->settings->get('company_email'),
            'company_phone' => $this->settings->get('company_phone'),
            'company_address' => $this->settings->get('company_address'),
            'currency_symbol' => $this->settings->get('currency_symbol', 'NPR'),
            'low_stock_threshold' => $this->settings->get('low_stock_threshold', 10),
            'smtp_host' => $this->settings->get('smtp_host', config('mail.mailers.smtp.host')),
            'smtp_port' => $this->settings->get('smtp_port', config('mail.mailers.smtp.port')),
            'smtp_username' => $this->settings->get('smtp_username'),
            'smtp_password' => '',
            'smtp_password_set' => $this->settings->has('smtp_password'),
            'smtp_encryption' => $this->settings->get('smtp_encryption', config('mail.mailers.smtp.encryption')),
            'mail_from_address' => $this->settings->get('mail_from_address', config('mail.from.address')),
            'mail_from_name' => $this->settings->get('mail_from_name', config('mail.from.name')),
            'notification_email' => $this->settings->get('notification_email'),
            'document_numbering' => DocumentNumberService::mergedSettings(),
        ];
    }

    public function update(array $data): void
    {
        if (array_key_exists('smtp_password', $data)) {
            if (! empty($data['smtp_password'])) {
                $this->settings->putSecret('smtp_password', $data['smtp_password']);
            }

            unset($data['smtp_password']);
        }

        if (isset($data['document_numbering'])) {
            $this->settings->put('document_numbering', $this->normalizeDocumentNumbering($data['document_numbering']));
            unset($data['document_numbering']);
        }

        foreach ($data as $key => $value) {
            $this->settings->put($key, $value);
        }
    }

    public function sendTestMail(?string $email = null): string
    {
        $recipient = $email
            ?? $this->settings->get('notification_email')
            ?? $this->settings->get('mail_from_address')
            ?? config('mail.from.address');

        if (empty($recipient)) {
            throw ValidationException::withMessages([
                'email' => 'Please add notification email or mail from address before testing.',
            ]);
        }

        try {
            Mail::raw('This is a test mail from the pharmacy management system. If you see this, SMTP is working.', function ($message) use ($recipient): void {
                $message->to($recipient)
                    ->subject('SMTP Test Mail');
            });
        } catch (Throwable $throwable) {
            throw ValidationException::withMessages([
                'email' => 'Test mail failed: '.$throwable->getMessage(),
            ]);
        }

        return $recipient;
    }

    private function normalizeDocumentNumbering(array $rows): array
    {
        return collect(DocumentNumberService::defaults())
            ->mapWithKeys(function (array $defaults, string $key) use ($rows): array {
                $row = is_array($rows[$key] ?? null) ? $rows[$key] : [];

                return [$key => [
                    'prefix' => $row['prefix'] ?? $defaults['prefix'],
                    'date_format' => $row['date_format'] ?? $defaults['date_format'],
                    'separator' => $row['separator'] ?? $defaults['separator'],
                    'padding' => (int) ($row['padding'] ?? $defaults['padding']),
                ]];
            })
            ->all();
    }
}
