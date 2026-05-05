<?php

namespace App\Modules\Inventory\Services;

use App\Core\Security\TenantRecordScope;
use App\Models\User;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Setup\Models\DropdownOption;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockAdjustmentService
{
    public function __construct(
        private readonly StockMovementService $stock,
        private readonly TenantRecordScope $records,
    ) {}

    public function save(array $data, User $user, ?StockAdjustment $adjustment = null): StockAdjustment
    {
        return DB::transaction(function () use ($data, $user, $adjustment) {
            if ($adjustment) {
                $this->assertAccessible($adjustment, $user, 'update');
            }

            $batch = Batch::query()->lockForUpdate();
            $this->records->apply($batch, $user);
            $batch = $batch->findOrFail($data['batch_id']);

            if ((int) $batch->product_id !== (int) $data['product_id']) {
                throw ValidationException::withMessages(['batch_id' => 'Selected batch does not belong to the selected product.']);
            }

            if ($adjustment) {
                $this->reverse($adjustment, $user, 'Adjustment edit rollback.');
            }

            $adjustment ??= new StockAdjustment;
            $adjustment->fill([
                'tenant_id' => $user->tenant_id,
                'company_id' => $user->company_id ?: $batch->company_id,
                'store_id' => $user->store_id ?: $batch->store_id,
                'adjustment_date' => $data['adjustment_date'],
                'product_id' => $batch->product_id,
                'batch_id' => $batch->id,
                'adjustment_type' => $data['adjustment_type'],
                'quantity' => $data['quantity'],
                'reason' => $data['reason'] ?? null,
                'adjusted_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            if (! $adjustment->exists) {
                $adjustment->created_by = $user->id;
            }

            $adjustment->save();
            $this->apply($adjustment, $user);

            return $adjustment->fresh(['product', 'batch', 'adjustedBy']);
        });
    }

    public function delete(StockAdjustment $adjustment, User $user): void
    {
        $this->assertAccessible($adjustment, $user, 'delete');

        DB::transaction(function () use ($adjustment, $user) {
            $this->reverse($adjustment, $user, 'Adjustment delete rollback.');
            $adjustment->delete();
        });
    }

    public function assertAccessible(StockAdjustment $adjustment, User $user, string $action = 'update'): void
    {
        $permission = $action === 'delete' ? 'inventory.batches.delete' : 'inventory.batches.update';

        abort_unless($user->is_owner || $user->can($permission), 403);

        if (! $this->records->canAccess($user, $adjustment)) {
            abort(404);
        }
    }

    private function apply(StockAdjustment $adjustment, User $user): void
    {
        $isInbound = $this->isInbound($adjustment->adjustment_type);

        $this->stock->record([
            'tenant_id' => $adjustment->tenant_id,
            'company_id' => $adjustment->company_id,
            'store_id' => $adjustment->store_id,
            'movement_date' => $adjustment->adjustment_date->toDateString(),
            'product_id' => $adjustment->product_id,
            'batch_id' => $adjustment->batch_id,
            'movement_type' => $isInbound ? 'adjustment_in' : 'adjustment_out',
            'quantity_in' => $isInbound ? $adjustment->quantity : 0,
            'quantity_out' => $isInbound ? 0 : $adjustment->quantity,
            'source_type' => 'stock_adjustment',
            'source_id' => $adjustment->id,
            'reference_type' => 'stock_adjustment',
            'reference_id' => $adjustment->id,
            'notes' => trim(ucfirst($adjustment->adjustment_type).' adjustment. '.($adjustment->reason ?? '')),
            'created_by' => $user->id,
        ]);
    }

    private function reverse(StockAdjustment $adjustment, User $user, string $note): void
    {
        $isInbound = $this->isInbound($adjustment->adjustment_type);

        $this->stock->record([
            'tenant_id' => $adjustment->tenant_id,
            'company_id' => $adjustment->company_id,
            'store_id' => $adjustment->store_id,
            'movement_date' => now()->toDateString(),
            'product_id' => $adjustment->product_id,
            'batch_id' => $adjustment->batch_id,
            'movement_type' => 'adjustment_reverse',
            'quantity_in' => $isInbound ? 0 : $adjustment->quantity,
            'quantity_out' => $isInbound ? $adjustment->quantity : 0,
            'source_type' => 'stock_adjustment',
            'source_id' => $adjustment->id,
            'reference_type' => 'stock_adjustment',
            'reference_id' => $adjustment->id,
            'notes' => $note,
            'created_by' => $user->id,
        ]);
    }

    private function isInbound(string $type): bool
    {
        if (in_array($type, ['add', 'return'], true)) {
            return true;
        }

        if (in_array($type, ['subtract', 'expired', 'damaged'], true)) {
            return false;
        }

        return DropdownOption::query()
            ->forAlias('adjustment_type')
            ->where('name', $type)
            ->value('data') === 'in';
    }
}
