<?php

namespace App\Modules\Setup\Http\Controllers;

use App\Core\Services\DocumentNumberService;
use App\Http\Controllers\ModularController;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * @OA\Tag(
 *     name="SETUP - Administration",
 *     description="API endpoints for SETUP - Administration"
 * )
 */
class SettingsAdminController extends ModularController
{
    // Return all app settings for the admin settings form.
    /**
     * @OA\Get(
     *     path="/settings/admin",
     *     summary="Api Settings Admin Show",
     *     tags={"SETUP - Admin"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
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
                'smtp_password' => '',
                'smtp_password_set' => Setting::hasValue('smtp_password'),
                'smtp_encryption' => Setting::getValue('smtp_encryption', config('mail.mailers.smtp.encryption')),
                'mail_from_address' => Setting::getValue('mail_from_address', config('mail.from.address')),
                'mail_from_name' => Setting::getValue('mail_from_name', config('mail.from.name')),
                'notification_email' => Setting::getValue('notification_email'),
                'document_numbering' => DocumentNumberService::mergedSettings(),
            ],
        ]);
    }

    // Save admin settings.
    /**
     * @OA\Put(
     *     path="/settings/admin",
     *     summary="Api Settings Admin Update",
     *     tags={"SETUP - Admin"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(required=false, @OA\JsonContent(type="object", additionalProperties=true)),
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
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
            'document_numbering' => ['nullable', 'array'],
            'document_numbering.*.prefix' => ['nullable', 'string', 'max:12'],
            'document_numbering.*.date_format' => ['nullable', 'in:Ymd,Ym,Y,none'],
            'document_numbering.*.separator' => ['nullable', Rule::in(['-', '/', '.', ''])],
            'document_numbering.*.padding' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        if (array_key_exists('smtp_password', $validated)) {
            if (! empty($validated['smtp_password'])) {
                Setting::putSecretValue('smtp_password', $validated['smtp_password']);
            }

            unset($validated['smtp_password']);
        }

        if (isset($validated['document_numbering'])) {
            Setting::putValue('document_numbering', $this->normalizeDocumentNumbering($validated['document_numbering']));
            unset($validated['document_numbering']);
        }

        foreach ($validated as $key => $value) {
            Setting::putValue($key, $value);
        }

        return response()->json([
            'message' => 'Settings saved successfully.',
        ]);
    }

    // Send one test mail to validate SMTP config.
    /**
     * @OA\Post(
     *     path="/settings/admin/test-mail",
     *     summary="Api Settings Admin Test Mail",
     *     tags={"SETUP - Admin"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(required=false, @OA\JsonContent(type="object", additionalProperties=true)),
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
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
                'message' => 'Test mail failed: '.$throwable->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Test mail sent to '.$recipient.'.',
        ]);
    }

    private function normalizeDocumentNumbering(array $rows): array
    {
        return collect(DocumentNumberService::defaults())
            ->mapWithKeys(function (array $defaults, string $key) use ($rows) {
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
