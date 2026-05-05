<?php

namespace App\Modules\Purchase\Services;

use App\Core\DTOs\TableQueryData;
use App\Core\Security\TenantRecordScope;
use App\Core\Services\DocumentNumberService;
use App\Models\Setting;
use App\Models\User;
use App\Modules\Accounting\Services\AccountTransactionPostingService;
use App\Modules\Inventory\Services\StockMovementService;
use App\Modules\Purchase\DTOs\PurchaseData;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Repositories\Interfaces\PurchaseRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseEntryService
{
    public function __construct(
        private readonly StockMovementService $stock,
        private readonly AccountTransactionPostingService $accounts,
        private readonly DocumentNumberService $numbers,
        private readonly PurchaseRepositoryInterface $purchases,
        private readonly TenantRecordScope $records,
    ) {}

    public function table(TableQueryData $table, ?User $user = null)
    {
        return $this->purchases->paginate($table, $user);
    }

    public function create(array $data, User $user): Purchase
    {
        $dto = PurchaseData::fromArray($data);

        return DB::transaction(function () use ($dto, $user) {
            $data = $dto->toArray();
            [$subtotal, $discountTotal, $grandTotal] = $this->totals($data['items']);
            $paidAmount = (float) ($data['paid_amount'] ?? 0);

            if ($paidAmount > $grandTotal) {
                throw ValidationException::withMessages(['paid_amount' => 'Paid amount cannot be greater than purchase total.']);
            }

            $purchase = $this->purchases->createPurchase([
                'tenant_id' => $user->tenant_id,
                'company_id' => $user->company_id,
                'store_id' => $user->store_id,
                'supplier_id' => $data['supplier_id'],
                'purchase_no' => $this->nextNumber($data['purchase_date'], $user),
                'supplier_invoice_no' => $data['supplier_invoice_no'] ?? null,
                'purchase_date' => $data['purchase_date'],
                'due_date' => $data['due_date'] ?? null,
                'status' => 'received',
                'payment_status' => $this->paymentStatus($grandTotal, $paidAmount),
                'payment_mode_id' => $data['payment_mode_id'] ?? null,
                'payment_type' => $data['payment_type'] ?? null,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'grand_total' => $grandTotal,
                'paid_amount' => $paidAmount,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            foreach ($data['items'] as $item) {
                $this->postItem($purchase, $item, $user);
            }

            $this->purchases->incrementSupplierBalance((int) $data['supplier_id'], round($grandTotal - $paidAmount, 2), $user, $purchase);

            $this->accounts->replaceForSource(
                $user,
                'purchase',
                $purchase->id,
                $purchase->purchase_date->toDateString(),
                $this->journalEntries($purchase, $paidAmount),
            );

            return $this->purchases->fresh($purchase);
        });
    }

    public function assertAccessible(Purchase $purchase, User $user): void
    {
        abort_unless($this->records->canAccess($user, $purchase), 404);
    }

    public function printPayload(Purchase $purchase): array
    {
        return [
            'purchase' => $purchase->load(['supplier', 'items.product', 'items.batch']),
            'branding' => Setting::getValue('app.branding', ['app_name' => 'PharmaNP']),
        ];
    }

    private function postItem(Purchase $purchase, array $item, User $user): void
    {
        $product = $this->purchases->productForUpdate((int) $item['product_id'], $user);
        $quantity = (float) $item['quantity'];
        $freeQuantity = (float) ($item['free_quantity'] ?? 0);
        $receivedQuantity = $quantity + $freeQuantity;
        $purchasePrice = (float) $item['purchase_price'];
        $mrp = (float) $item['mrp'];
        $ccRate = (float) ($item['cc_rate'] ?? $product->cc_rate ?? 0);
        $discountPercent = (float) ($item['discount_percent'] ?? 0);
        $gross = $quantity * $purchasePrice;
        $discount = round($gross * $discountPercent / 100, 2);
        $freeGoodsValue = round($freeQuantity * ($mrp * $ccRate / 100), 2);
        $lineTotal = round($gross - $discount, 2);

        $batch = $this->purchases->batchForPurchase((int) $purchase->company_id, (int) $product->id, (string) $item['batch_no'], $user, $purchase);

        if (! $batch) {
            $batch = $this->purchases->createBatch([
                'tenant_id' => $purchase->tenant_id,
                'company_id' => $purchase->company_id,
                'store_id' => $purchase->store_id,
                'product_id' => $product->id,
                'supplier_id' => $purchase->supplier_id,
                'purchase_id' => $purchase->id,
                'batch_no' => $item['batch_no'],
                'barcode' => $item['barcode'] ?? null,
                'manufactured_at' => $item['manufactured_at'] ?? null,
                'expires_at' => $item['expires_at'],
                'quantity_received' => 0,
                'quantity_available' => 0,
                'purchase_price' => $purchasePrice,
                'mrp' => $mrp,
                'is_active' => true,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        }

        $batch = $this->purchases->saveBatch($batch, [
            'supplier_id' => $purchase->supplier_id,
            'purchase_id' => $purchase->id,
            'barcode' => $item['barcode'] ?? $batch->barcode,
            'manufactured_at' => $item['manufactured_at'] ?? $batch->manufactured_at,
            'expires_at' => $item['expires_at'],
            'quantity_received' => (float) $batch->quantity_received + $receivedQuantity,
            'purchase_price' => $purchasePrice,
            'mrp' => $mrp,
            'updated_by' => $user->id,
        ]);

        $this->purchases->createItem($purchase, [
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'batch_no' => $batch->batch_no,
            'manufactured_at' => $item['manufactured_at'] ?? null,
            'expires_at' => $item['expires_at'],
            'quantity' => $quantity,
            'free_quantity' => $freeQuantity,
            'purchase_price' => $purchasePrice,
            'mrp' => $mrp,
            'cc_rate' => $ccRate,
            'discount_percent' => $discountPercent,
            'discount_amount' => $discount,
            'free_goods_value' => $freeGoodsValue,
            'line_total' => $lineTotal,
        ]);

        $this->purchases->saveProduct($product, [
            'purchase_price' => $purchasePrice,
            'mrp' => $mrp,
            'selling_price' => max((float) $product->selling_price, $mrp),
            'updated_by' => $user->id,
        ]);

        $this->stock->record([
            'tenant_id' => $purchase->tenant_id,
            'company_id' => $purchase->company_id,
            'store_id' => $purchase->store_id,
            'movement_date' => $purchase->purchase_date->toDateString(),
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'movement_type' => 'purchase_receive',
            'quantity_in' => $receivedQuantity,
            'quantity_out' => 0,
            'source_type' => 'purchase',
            'source_id' => $purchase->id,
            'reference_type' => 'batch',
            'reference_id' => $batch->id,
            'notes' => 'Purchase '.$purchase->purchase_no,
            'created_by' => $user->id,
        ]);
    }

    private function totals(array $items): array
    {
        $subtotal = 0;
        $discountTotal = 0;

        foreach ($items as $item) {
            $gross = (float) $item['quantity'] * (float) $item['purchase_price'];
            $discount = round($gross * (float) ($item['discount_percent'] ?? 0) / 100, 2);
            $subtotal += $gross;
            $discountTotal += $discount;
        }

        return [round($subtotal, 2), round($discountTotal, 2), round($subtotal - $discountTotal, 2)];
    }

    private function paymentStatus(float $total, float $paid): string
    {
        if ($paid <= 0) {
            return 'unpaid';
        }

        return $paid >= $total ? 'paid' : 'partial';
    }

    private function nextNumber(string $purchaseDate, User $user): string
    {
        return $this->numbers->next('purchase', 'purchases', Carbon::parse($purchaseDate), $user);
    }

    private function journalEntries(Purchase $purchase, float $paidAmount): array
    {
        $entries = [[
            'account_type' => 'inventory',
            'debit' => (float) $purchase->grand_total,
            'credit' => 0,
            'notes' => 'Inventory received '.$purchase->purchase_no,
        ]];

        if ($paidAmount > 0) {
            $entries[] = [
                'account_type' => 'cash',
                'debit' => 0,
                'credit' => $paidAmount,
                'notes' => 'Paid on '.$purchase->purchase_no,
            ];
        }

        $outstanding = round((float) $purchase->grand_total - $paidAmount, 2);

        if ($outstanding > 0) {
            $entries[] = [
                'account_type' => 'payable',
                'party_type' => 'supplier',
                'party_id' => $purchase->supplier_id,
                'debit' => 0,
                'credit' => $outstanding,
                'notes' => 'Outstanding on '.$purchase->purchase_no,
            ];
        }

        return $entries;
    }
}
