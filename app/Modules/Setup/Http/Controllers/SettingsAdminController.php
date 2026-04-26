<?php

namespace App\Modules\Setup\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SettingsAdminController
{
    // Return all app settings for the admin settings form.
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => [
                'company_email' => Setting::getValue('company_email'),
                'company_phone' => Setting::getValue('company_phone'),
                'company_address' => Setting::getValue('company_address'),
                'currency_symbol' => Setting::getValue('currency_symbol', 'NPR'),
                'low_stock_threshold' => Setting::getValue('low_stock_threshold', 10),
                'smtp_host' => Setting::getValue('smtp_host', config('mail.mailers.smtp.host')),
                'smtp_port' => Setting::getValue('smtp_port', config('mail.mailers.smtp.port')),
                'smtp_username' => Setting::getValue('smtp_username'),
                'smtp_password' => Setting::getValue('smtp_password'),
                'smtp_encryption' => Setting::getValue('smtp_encryption', config('mail.mailers.smtp.encryption')),
                'mail_from_address' => Setting::getValue('mail_from_address', config('mail.from.address')),
                'mail_from_name' => Setting::getValue('mail_from_name', config('mail.from.name')),
                'notification_email' => Setting::getValue('notification_email'),
            ],
        ]);
    }

    // Save admin settings.
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_email' => ['nullable', 'email'],
            'company_phone' => ['nullable', 'string', 'max:255'],
            'company_address' => ['nullable', 'string', 'max:5000'],
            'currency_symbol' => ['nullable', 'string', 'max:20'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:1'],
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'string', 'max:255'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'smtp_encryption' => ['nullable', 'string', 'max:255'],
            'mail_from_address' => ['nullable', 'email'],
            'mail_from_name' => ['nullable', 'string', 'max:255'],
            'notification_email' => ['nullable', 'email'],
        ]);

        foreach ($validated as $key => $value) {
            Setting::putValue($key, $value);
        }

        return response()->json([
            'message' => 'Settings saved successfully.',
        ]);
    }

    // Send one test mail to validate SMTP config.
    public function testMail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['nullable', 'email'],
        ]);

        $recipient = $validated['email']
            ?? Setting::getValue('notification_email')
            ?? Setting::getValue('mail_from_address')
            ?? config('mail.from.address');

        if (empty($recipient)) {
            return response()->json([
                'message' => 'Please add notification email or mail from address before testing.',
            ], 422);
        }

        try {
            Mail::raw('This is a test mail from the pharmacy management system. If you see this, SMTP is working.', function ($message) use ($recipient) {
                $message->to($recipient)
                    ->subject('SMTP Test Mail');
            });
        } catch (Throwable $throwable) {
            return response()->json([
                'message' => 'Test mail failed: ' . $throwable->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Test mail sent to ' . $recipient . '.',
        ]);
    }
}
