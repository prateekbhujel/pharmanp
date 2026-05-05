<?php

namespace App\Modules\Purchase\Services;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Core\DTOs\TableQueryData;
use App\Core\Security\TenantRecordScope;
use App\Core\Services\DocumentNumberService;
use App\Models\Setting;
use App\Models\User;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Accounting\Services\AccountTransactionPostingService;
use App\Modules\Inventory\Services\StockMovementService;
use App\Modules\Purchase\DTOs\PurchaseReturnData;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Models\PurchaseItem;
use App\Modules\Purchase\Models\PurchaseReturn;
use App\Modules\Purchase\Models\PurchaseReturnItem;
use App\Modules\Purchase\Repositories\Interfaces\PurchaseReturnRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseReturnService
{
    public function __construct(
        private readonly StockMovementService $stock,
        private readonly AccountTransactionPostingService $accounts,
        private readonly PurchaseReturnRepositoryInterface $returns,
        private readonly TenantRecordScope $scope,
        private readonly DocumentNumberService $numbers,
    ) {}

    public function table(TableQueryData $table, ?User $user = null): LengthAwarePaginator
    {
        return $this->returns->paginate($table, $user);
    }

    public function save(array $data, User $user, ?PurchaseReturn $purchaseReturn = null): PurchaseReturn
    {
        $dto = PurchaseReturnData::fromArray($data);

        return DB::transaction(function () use ($dto, $user, $purchaseReturn) {
            $data = $dto->toArray();
            $purchase = ! empty($data['purchase_id'])
                ? $this->returns->purchase((int) $data['purchase_id'], $user)
                : null;

            if ($purchase && (int) $purchase->supplier_id !== (int) $data['supplier_id']) {
                throw ValidationException::withMessages(['purchase_id' => 'Selected purchase does not belong to this supplier.']);
            }

            if ($purchaseReturn) {
                $this->assertAccessible($purchaseReturn, $user);
                $purchaseReturn->load('items');
                $this->restoreStock($purchaseReturn, $user, false);
                $this->returns->adjustSupplierBalance((int) $purchaseReturn->supplier_id, (float) $purchaseReturn->grand_total, $user, $purchaseReturn);
                $this->returns->deleteItems($purchaseReturn);
            }

            $rows = collect($data['items'])
                ->filter(fn (array $row) => (float) ($row['return_qty'] ?? 0) > 0)
                ->values();

            if ($rows->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Please enter return quantity for at least one line item.']);
            }

            $purchaseReturn ??= new PurchaseReturn;
            $purchaseReturn = $this->returns->save($purchaseReturn, [
                'tenant_id' => $user->tenant_id,
                'company_id' => $user->company_id,
                'store_id' => $user->store_id,
                'purchase_id' => $purchase?->id,
                'supplier_id' => $data['supplier_id'],
                'return_no' => $purchaseReturn->return_no ?: $this->nextNumber($data['return_date'], $user),
                'return_type' => $data['return_type'] ?? 'regular',
                'return_date' => $data['return_date'],
                'status' => 'posted',
                'subtotal' => 0,
                'discount_total' => 0,
                'grand_total' => 0,
                'notes' => $data['notes'] ?? null,
                'returned_by' => $user->id,
                'created_by' => $purchaseReturn->exists ? $purchaseReturn->created_by : $user->id,
                'updated_by' => $user->id,
            ]);

            $subtotal = 0;
            $discountTotal = 0;
            $grandTotal = 0;
            foreach ($rows as $row) {
                [$lineSubtotal, $lineDiscount, $lineTotal] = $this->postItem($purchaseReturn, $purchase, $row, $user);
                $subtotal += $lineSubtotal;
                $discountTotal += $lineDiscount;
                $grandTotal += $lineTotal;
            }

            $purchaseReturn = $this->returns->save($purchaseReturn, [
                'subtotal' => round($subtotal, 2),
                'discount_total' => round($discountTotal, 2),
                'grand_total' => round($grandTotal, 2),
            ]);

            $this->returns->adjustSupplierBalance((int) $purchaseReturn->supplier_id, -1 * (float) $purchaseReturn->grand_total, $user, $purchaseReturn);

            $this->accounts->replaceForSource(
                $user,
                'purchase_return',
                $purchaseReturn->id,
                $purchaseReturn->return_date->toDateString(),
                $this->journalEntries($purchaseReturn),
            );

            return $this->returns->fresh($purchaseReturn);
        });
    }

    public function delete(PurchaseReturn $purchaseReturn, User $user): void
    {
        $this->assertAccessible($purchaseReturn, $user);

        DB::transaction(function () use ($purchaseReturn, $user) {
            $purchaseReturn->load('items');
            $this->restoreStock($purchaseReturn, $user, true);

            $this->returns->adjustSupplierBalance((int) $purchaseReturn->supplier_id, (float) $purchaseReturn->grand_total, $user, $purchaseReturn);

            $this->accounts->replaceForSource(
                $user,
                'purchase_return',
                $purchaseReturn->id,
                now()->toDateString(),
                [],
            );

            $this->returns->deleteItems($purchaseReturn);
            $this->returns->delete($purchaseReturn);
        });
    }

    public function assertAccessible(PurchaseReturn $purchaseReturn, User $user): void
    {
        abort_unless($this->scope->canAccess($user, $purchaseReturn), 404);
    }

    public function purchaseOptions(User $user, ?int $supplierId): array
    {
        return Purchase::query()
            ->when(! $user->canAccessAllTenants() && $user->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('tenant_id', $tenantId))
            ->when(! $user->canAccessAllTenants() && $user->company_id, fn (Builder $builder, int $companyId) => $builder->where('company_id', $companyId))
            ->when(! $user->canAccessAllTenants() && $user->store_id, fn (Builder $builder, int $storeId) => $builder->where('store_id', $storeId))
            ->where('supplier_id', $supplierId)
            ->latest('purchase_date')
            ->limit(100)
            ->get(['id', 'purchase_no', 'supplier_invoice_no', 'purchase_date', 'grand_total'])
            ->map(fn (Purchase $purchase) => [
                'id' => $purchase->id,
                'label' => trim($purchase->purchase_no.' | '.($purchase->supplier_invoice_no ?: 'No supplier bill').' | '.$purchase->purchase_date?->toDateString().' | Rs. '.number_format((float) $purchase->grand_total, 2)),
            ])
            ->values()
            ->all();
    }

    public function purchaseItems(Purchase $purchase, User $user): array
    {
        abort_unless($this->scope->canAccess($user, $purchase), 404);

        return PurchaseItem::query()
            ->with(['product:id,name', 'batch:id,batch_no,expires_at,quantity_available,purchase_price,mrp'])
            ->where('purchase_id', $purchase->id)
            ->get()
            ->map(function (PurchaseItem $item) use ($purchase, $user) {
                $alreadyReturned = (float) PurchaseReturnItem::query()
                    ->where('purchase_item_id', $item->id)
                    ->sum('return_qty');
                $originalQty = (float) $item->quantity + (float) $item->free_quantity;
                $maxReturnable = max(0, $originalQty - $alreadyReturned);
                $returnQty = $maxReturnable > 0 ? 1 : 0;
                $rate = (float) $item->purchase_price;
                $netRate = round($rate - ($rate * (float) $item->discount_percent / 100), 2);

                return [
                    'purchase_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product?->name,
                    'batch_id' => $item->batch_id,
                    'batch_no' => $item->batch?->batch_no,
                    'original_qty' => $originalQty,
                    'already_returned' => $alreadyReturned,
                    'max_returnable' => $maxReturnable,
                    'return_qty' => $returnQty,
                    'rate' => $rate,
                    'discount_percent' => (float) $item->discount_percent,
                    'discount_amount' => round($returnQty * max(0, $rate - $netRate), 2),
                    'net_rate' => $netRate,
                    'return_amount' => round($returnQty * $netRate, 2),
                    'batch_options' => $this->batchOptions($item->product_id, $purchase->supplier_id, $item->batch_id, $user),
                ];
            })
            ->values()
            ->all();
    }

    public function batchOptions(?int $productId, ?int $supplierId, ?int $selectedBatchId, User $user): array
    {
        return Batch::query()
            ->when(! $user->canAccessAllTenants() && $user->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('tenant_id', $tenantId))
            ->when(! $user->canAccessAllTenants() && $user->company_id, fn (Builder $builder, int $companyId) => $builder->where('company_id', $companyId))
            ->when(! $user->canAccessAllTenants() && $user->store_id, fn (Builder $builder, int $storeId) => $builder->where('store_id', $storeId))
            ->where(function (Builder $query) use ($productId, $supplierId, $selectedBatchId) {
                $query->where(function (Builder $available) use ($productId, $supplierId) {
                    $available->where('is_active', true)
                        ->where('quantity_available', '>', 0)
                        ->when($productId, fn (Builder $builder) => $builder->where('product_id', $productId))
                        ->when($supplierId, fn (Builder $builder) => $builder->where('supplier_id', $supplierId));
                });

                if ($selectedBatchId) {
                    $query->orWhere('id', $selectedBatchId);
                }
            })
            ->orderBy('expires_at')
            ->orderBy('batch_no')
            ->limit(100)
            ->get()
            ->map(fn (Batch $batch) => [
                'id' => $batch->id,
                'label' => trim($batch->batch_no.' | Exp: '.($batch->expires_at?->toDateString() ?: '-').' | Qty: '.number_format((float) $batch->quantity_available, 3)),
                'product_id' => $batch->product_id,
                'batch_no' => $batch->batch_no,
                'expires_at' => $batch->expires_at?->toDateString(),
                'quantity_available' => (float) $batch->quantity_available,
                'purchase_price' => (float) $batch->purchase_price,
                'mrp' => (float) $batch->mrp,
            ])
            ->values()
            ->all();
    }

    public function printPayload(PurchaseReturn $purchaseReturn): array
    {
        return [
            'purchaseReturn' => $purchaseReturn->load(['supplier', 'purchase', 'items.product', 'items.batch']),
            'branding' => Setting::getValue('app.branding', ['app_name' => 'PharmaNP']),
        ];
    }

    private function postItem(PurchaseReturn $purchaseReturn, ?Purchase $purchase, array $row, User $user): array
    {
        $batch = $this->returns->batchForUpdate((int) $row['batch_id'], $user, $purchaseReturn);
        $returnQty = (float) $row['return_qty'];

        if ((int) $batch->supplier_id !== (int) $purchaseReturn->supplier_id) {
            throw ValidationException::withMessages(['items' => 'Selected batch does not belong to the selected supplier.']);
        }

        if ((int) $batch->product_id !== (int) $row['product_id']) {
            throw ValidationException::withMessages(['items' => 'Selected batch does not belong to the selected product.']);
        }

        $purchaseItem = null;
        $rate = (float) ($row['rate'] ?? $batch->purchase_price);
        $discountPercent = (float) ($row['discount_percent'] ?? 0);

        if ($purchase && ! empty($row['purchase_item_id'])) {
            $purchaseItem = $this->returns->purchaseItem((int) $purchase->id, (int) $row['purchase_item_id'], $user);

            if ((int) $purchaseItem->product_id !== (int) $row['product_id']) {
                throw ValidationException::withMessages(['items' => 'Purchase line product does not match the return row.']);
            }

            $alreadyReturned = $this->returns->returnedQuantityForItem((int) $purchaseItem->id, (int) $purchaseReturn->id);

            $maxReturnable = max(0, (float) $purchaseItem->quantity + (float) $purchaseItem->free_quantity - $alreadyReturned);

            if ($returnQty > $maxReturnable) {
                throw ValidationException::withMessages(['items' => 'Return quantity cannot exceed remaining returnable quantity.']);
            }

            $rate = (float) $purchaseItem->purchase_price;
            $discountPercent = (float) ($row['discount_percent'] ?? $purchaseItem->discount_percent ?? 0);
        }

        if ((float) $batch->quantity_available < $returnQty) {
            throw ValidationException::withMessages(['items' => 'Selected batch does not have enough stock for this return.']);
        }

        [$discountAmount, $netRate, $returnAmount, $discountPercent] = $this->lineAmounts($returnQty, $rate, $discountPercent, $row);

        $this->returns->createItem([
            'purchase_return_id' => $purchaseReturn->id,
            'purchase_item_id' => $purchaseItem?->id,
            'batch_id' => $batch->id,
            'product_id' => (int) $row['product_id'],
            'return_qty' => $returnQty,
            'rate' => $rate,
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountAmount,
            'net_rate' => $netRate,
            'return_amount' => $returnAmount,
        ]);

        $this->stock->record([
            'tenant_id' => $purchaseReturn->tenant_id,
            'company_id' => $purchaseReturn->company_id,
            'store_id' => $purchaseReturn->store_id,
            'movement_date' => $purchaseReturn->return_date->toDateString(),
            'product_id' => (int) $row['product_id'],
            'batch_id' => $batch->id,
            'movement_type' => 'purchase_return_out',
            'quantity_in' => 0,
            'quantity_out' => $returnQty,
            'source_type' => 'purchase_return',
            'source_id' => $purchaseReturn->id,
            'reference_type' => 'purchase_return',
            'reference_id' => $purchaseReturn->id,
            'notes' => 'Purchase return '.$purchaseReturn->return_no,
            'created_by' => $user->id,
        ]);

        return [round($returnQty * $rate, 2), $discountAmount, $returnAmount];
    }

    private function restoreStock(PurchaseReturn $purchaseReturn, User $user, bool $finalDelete): void
    {
        foreach ($purchaseReturn->items as $item) {
            $this->stock->record([
                'tenant_id' => $purchaseReturn->tenant_id,
                'company_id' => $purchaseReturn->company_id,
                'store_id' => $purchaseReturn->store_id,
                'movement_date' => now()->toDateString(),
                'product_id' => $item->product_id,
                'batch_id' => $item->batch_id,
                'movement_type' => 'purchase_return_reverse',
                'quantity_in' => $item->return_qty,
                'quantity_out' => 0,
                'source_type' => 'purchase_return',
                'source_id' => $purchaseReturn->id,
                'reference_type' => 'purchase_return',
                'reference_id' => $purchaseReturn->id,
                'notes' => $finalDelete ? 'Purchase return delete restored stock.' : 'Purchase return edit restored stock.',
                'created_by' => $user->id,
            ]);
        }
    }

    private function totals(array $items): array
    {
        $subtotal = 0;
        $discountTotal = 0;
        $grandTotal = 0;

        foreach ($items as $item) {
            $quantity = (float) $item['return_qty'];
            $rate = (float) ($item['rate'] ?? 0);
            $discountPercent = (float) ($item['discount_percent'] ?? 0);
            [$discountAmount, , $returnAmount] = $this->lineAmounts($quantity, $rate, $discountPercent, $item);

            $subtotal += $quantity * $rate;
            $discountTotal += $discountAmount;
            $grandTotal += $returnAmount;
        }

        return [round($subtotal, 2), round($discountTotal, 2), round($grandTotal, 2)];
    }

    private function lineAmounts(float $quantity, float $rate, float $discountPercent, array $row): array
    {
        $discountPercent = max(0, min(100, $discountPercent));
        $discountAmount = round($quantity * $rate * $discountPercent / 100, 2);
        $netRate = round(max(0, $rate - ($rate * $discountPercent / 100)), 2);

        if (isset($row['net_rate']) && $row['net_rate'] !== '' && $row['net_rate'] !== null) {
            $netRate = round(max(0, min($rate, (float) $row['net_rate'])), 2);
            $discountAmount = round(max(0, ($rate - $netRate) * $quantity), 2);
            $discountPercent = $rate > 0 ? round((($rate - $netRate) / $rate) * 100, 2) : 0;
        } elseif (isset($row['discount_amount']) && $row['discount_amount'] !== '' && $row['discount_amount'] !== null) {
            $discountAmount = round(max(0, (float) $row['discount_amount']), 2);
            $netRate = round(max(0, $rate - ($quantity > 0 ? $discountAmount / $quantity : 0)), 2);
            $discountPercent = $rate > 0 ? round((($rate - $netRate) / $rate) * 100, 2) : 0;
        }

        return [$discountAmount, $netRate, round($quantity * $netRate, 2), $discountPercent];
    }

    private function nextNumber(string $returnDate, User $user): string
    {
        return $this->numbers->next('purchase_return', 'purchase_returns', Carbon::parse($returnDate), $user);
    }

    private function journalEntries(PurchaseReturn $purchaseReturn): array
    {
        return [
            [
                'account_type' => 'payable',
                'party_type' => 'supplier',
                'party_id' => $purchaseReturn->supplier_id,
                'debit' => (float) $purchaseReturn->grand_total,
                'credit' => 0,
                'notes' => 'Supplier payable reduced '.$purchaseReturn->return_no,
            ],
            [
                'account_type' => 'inventory',
                'debit' => 0,
                'credit' => (float) $purchaseReturn->grand_total,
                'notes' => 'Inventory sent back '.$purchaseReturn->return_no,
            ],
        ];
    }
}
