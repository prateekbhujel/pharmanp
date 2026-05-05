<?php

namespace App\Modules\ImportExport\Services;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Modules\Party\Models\Supplier;
use App\Modules\Purchase\Models\Purchase;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PurchaseOcrService
{
    public function extract(UploadedFile $file): array
    {
        $apiKey = (string) config('services.ocr_space.key', 'helloworld');
        $endpoint = (string) config('services.ocr_space.endpoint', 'https://api.ocr.space/parse/image');
        $providerMaxKb = max(1, (int) config('services.ocr_space.provider_max_kb', 1024));
        $fileSizeKb = (int) ceil(($file->getSize() ?: 0) / 1024);

        if ($fileSizeKb > $providerMaxKb) {
            return $this->failedResult(
                $file,
                'OCR provider limit exceeded. Current OCR.space limit is configured as '.$providerMaxKb.' KB; this file is '.$fileSizeKb.' KB. Use a paid OCR.space key with a higher OCR_SPACE_MAX_KB value, or compress/scan the bill smaller.',
                [
                    'file_size_kb' => $fileSizeKb,
                    'provider_max_kb' => $providerMaxKb,
                    'provider' => 'ocr.space',
                ],
            );
        }

        $payload = null;
        $text = '';
        $errorMessage = null;

        foreach ([2, 1] as $engine) {
            $handle = fopen($file->getRealPath(), 'r');

            try {
                $response = Http::timeout(120)
                    ->acceptJson()
                    ->attach('file', $handle, $file->getClientOriginalName())
                    ->post($endpoint, [
                        'apikey' => $apiKey,
                        'language' => 'eng',
                        'isOverlayRequired' => 'false',
                        'OCREngine' => (string) $engine,
                        'scale' => 'true',
                        'detectOrientation' => 'true',
                        'filetype' => Str::lower($file->getClientOriginalExtension()),
                    ]);
            } finally {
                if (is_resource($handle)) {
                    fclose($handle);
                }
            }

            $payload = $response->json();
            $rawBody = $response->body();

            if (! $response->ok() || ! empty($payload['IsErroredOnProcessing'])) {
                $errorMessage = $this->failureMessage($payload ?: $rawBody) ?: $errorMessage;

                continue;
            }

            $parsedResults = $payload['ParsedResults'] ?? [];
            $text = trim(collect($parsedResults)->pluck('ParsedText')->implode("\n"));

            if ($text !== '') {
                break;
            }
        }

        $lines = collect(preg_split('/\r\n|\r|\n/', $text ?: ''))
            ->map(fn ($line) => trim((string) $line))
            ->filter()
            ->values()
            ->all();

        if ($text === '') {
            return $this->failedResult(
                $file,
                $errorMessage ?: 'OCR could not read this image clearly. Try a clearer scan or continue manually.',
                [
                    'file_size_kb' => $fileSizeKb,
                    'provider_max_kb' => $providerMaxKb,
                    'provider' => 'ocr.space',
                ],
            );
        }

        $analysis = $this->analyzeInvoiceText($text, $lines);
        $matches = $this->findMatchingPurchases($analysis);
        $analysis['bill_state'] = empty($matches)
            ? ($analysis['supplier_id'] || $analysis['invoice_no'] ? 'new_bill' : 'manual_review')
            : 'matched_bill';
        $analysis['next_action'] = empty($matches)
            ? ($analysis['supplier_id'] || $analysis['invoice_no'] ? 'create_new' : 'fill_manually')
            : 'open_existing';
        $analysis['match_count'] = count($matches);

        return [
            'file_name' => $file->getClientOriginalName(),
            'text' => $text,
            'lines' => $lines,
            'analysis' => $analysis,
            'matches' => $matches,
            'ocr_limits' => [
                'file_size_kb' => $fileSizeKb,
                'provider_max_kb' => $providerMaxKb,
                'provider' => 'ocr.space',
            ],
            'extraction_status' => 'success',
        ];
    }

    public function draftPurchase(array $data): array
    {
        $analysis = $data['analysis'] ?? [];

        return [
            'supplier_id' => $analysis['supplier_id'] ?? null,
            'supplier_name' => $analysis['supplier_name'] ?? null,
            'supplier_invoice_no' => $analysis['invoice_no'] ?? '',
            'purchase_date' => $analysis['invoice_date'] ?? null,
            'notes' => $data['ocr_text'] ?? '',
            'selected_purchase_id' => $data['selected_purchase_id'] ?? null,
            'matches' => $data['matches'] ?? [],
            'analysis' => $analysis,
            'created_at' => now()->toISOString(),
        ];
    }

    private function failedResult(UploadedFile $file, string $message, array $limits = []): array
    {
        return [
            'file_name' => $file->getClientOriginalName(),
            'text' => '',
            'lines' => [],
            'analysis' => [
                'document_type' => 'unknown',
                'invoice_no' => null,
                'invoice_date' => null,
                'supplier_id' => null,
                'supplier_name' => null,
                'total_amount' => null,
                'confidence' => 0,
                'bill_state' => 'manual_review',
                'next_action' => 'fill_manually',
                'match_count' => 0,
            ],
            'matches' => [],
            'ocr_limits' => $limits,
            'extraction_status' => 'failed',
            'failure_message' => $message,
        ];
    }

    private function analyzeInvoiceText(string $text, array $lines): array
    {
        $normalized = Str::lower($text);
        $supplier = $this->matchSupplier($normalized);
        $invoiceNo = $this->extractInvoiceNumber($lines, $normalized);
        $invoiceDate = $this->extractInvoiceDate($lines);
        $totalAmount = $this->extractTotalAmount($lines);
        $documentType = $this->detectDocumentType($normalized);

        $confidence = 0;
        $confidence += $invoiceNo ? 25 : 0;
        $confidence += $invoiceDate ? 20 : 0;
        $confidence += $supplier ? 25 : 0;
        $confidence += $totalAmount !== null ? 15 : 0;
        $confidence += $documentType !== 'unknown' ? 15 : 0;

        return [
            'document_type' => $documentType,
            'invoice_no' => $invoiceNo,
            'invoice_date' => $invoiceDate,
            'supplier_id' => $supplier['id'] ?? null,
            'supplier_name' => $supplier['name'] ?? null,
            'total_amount' => $totalAmount,
            'confidence' => min(100, $confidence),
        ];
    }

    private function matchSupplier(string $normalizedText): ?array
    {
        $suppliers = Supplier::query()
            ->where('is_active', true)
            ->get(['id', 'name'])
            ->map(fn (Supplier $supplier) => ['id' => $supplier->id, 'name' => trim((string) $supplier->name)]);

        foreach ($suppliers as $supplier) {
            $needle = Str::lower($supplier['name']);

            if ($needle !== '' && Str::contains($normalizedText, $needle)) {
                return $supplier;
            }
        }

        return null;
    }

    private function extractInvoiceNumber(array $lines, string $normalizedText): ?string
    {
        foreach ($lines as $line) {
            if (preg_match('/(?:invoice|bill|ref|reference)\s*(?:no\.?|number|#|:)?\s*([a-z0-9\-\/]+)/i', $line, $matches)) {
                return trim($matches[1]);
            }
        }

        if (preg_match('/(?:invoice|bill)\s*#?\s*([a-z0-9\-\/]+)/i', $normalizedText, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private function extractInvoiceDate(array $lines): ?string
    {
        foreach ($lines as $line) {
            if (! preg_match('/(?:\b\d{1,2}[\/\-.]\d{1,2}[\/\-.]\d{2,4}\b|\b\d{4}[\/\-.]\d{1,2}[\/\-.]\d{1,2}\b)/', trim((string) $line), $matches)) {
                continue;
            }

            try {
                return Carbon::parse($matches[0])->format('Y-m-d');
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    private function extractTotalAmount(array $lines): ?float
    {
        $candidates = collect($lines)
            ->reverse()
            ->filter(fn ($line) => Str::contains(Str::lower((string) $line), ['grand total', 'net amount', 'total amount', 'bill amount', 'amount']));

        foreach ($candidates as $line) {
            if (preg_match('/(\d[\d,]*\.?\d{0,2})\s*$/', (string) $line, $matches)) {
                return (float) str_replace(',', '', $matches[1]);
            }
        }

        return null;
    }

    private function detectDocumentType(string $normalizedText): string
    {
        $patterns = [
            'purchase_invoice' => ['purchase invoice', 'tax invoice', 'invoice', 'bill'],
            'purchase_order' => ['purchase order', 'po no', 'p.o.'],
            'receipt' => ['receipt', 'payment receipt', 'cash memo'],
            'delivery_note' => ['delivery note', 'dispatch note', 'challan'],
        ];

        foreach ($patterns as $type => $needles) {
            foreach ($needles as $needle) {
                if (Str::contains($normalizedText, $needle)) {
                    return $type;
                }
            }
        }

        return 'unknown';
    }

    private function findMatchingPurchases(array $analysis): array
    {
        $query = Purchase::query()->with('supplier');

        if (! empty($analysis['supplier_id'])) {
            $query->where('supplier_id', $analysis['supplier_id']);
        }

        if (! empty($analysis['invoice_no'])) {
            $invoiceNo = trim((string) $analysis['invoice_no']);
            $query->where('supplier_invoice_no', 'like', '%'.$invoiceNo.'%');
        }

        return $query
            ->latest('purchase_date')
            ->limit(5)
            ->get()
            ->map(fn (Purchase $purchase) => [
                'id' => $purchase->id,
                'purchase_no' => $purchase->purchase_no,
                'invoice_no' => $purchase->supplier_invoice_no ?: '-',
                'supplier_name' => $purchase->supplier?->name ?? '-',
                'purchase_date' => $purchase->purchase_date?->toDateString() ?: '-',
                'grand_total' => round((float) $purchase->grand_total, 2),
                'paid_amount' => round((float) $purchase->paid_amount, 2),
                'payment_status' => $purchase->payment_status,
            ])
            ->values()
            ->all();
    }

    private function failureMessage(array|string|null $payload): ?string
    {
        if (empty($payload)) {
            return null;
        }

        if (is_string($payload)) {
            $decoded = json_decode($payload, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $payload = $decoded;
            } else {
                $payload = trim(strip_tags($payload));
                $payload = preg_replace('/\s+/', ' ', $payload);

                return $payload !== '' ? Str::limit((string) $payload, 180) : null;
            }
        }

        $errors = $payload['ErrorMessage'] ?? $payload['ErrorDetails'] ?? null;
        if (is_array($errors)) {
            $errors = implode(' ', array_filter(array_map('strval', $errors)));
        }

        $errors = trim((string) $errors);

        return $errors !== '' ? $errors : null;
    }
}
