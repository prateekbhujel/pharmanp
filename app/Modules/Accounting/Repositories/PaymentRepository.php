<?php

namespace App\Modules\Accounting\Repositories;

use App\Models\User;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Models\PaymentBillAllocation;
use App\Modules\Accounting\Repositories\Interfaces\PaymentRepositoryInterface;
use App\Modules\Party\Models\Customer;
use App\Modules\Party\Models\Supplier;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Setup\Models\DropdownOption;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PaymentRepository implements PaymentRepositoryInterface
{
    public function getForSettlement(?int $id = null): Payment
    {
        return $id
            ? Payment::query()->with('allocations')->lockForUpdate()->findOrFail($id)
            : new Payment;
    }

    public function deleteAllocations(int $paymentId): void
    {
        PaymentBillAllocation::query()->where('payment_id', $paymentId)->delete();
    }

    public function createAllocation(array $data): PaymentBillAllocation
    {
        return PaymentBillAllocation::query()->create($data);
    }

    public function paymentMode(int $id): DropdownOption
    {
        return DropdownOption::query()
            ->forAlias('payment_mode')
            ->active()
            ->findOrFail($id);
    }

    public function outstandingCustomerBills(int $customerId, ?User $user = null): Collection
    {
        return SalesInvoice::query()
            ->where('customer_id', $customerId)
            ->when($user?->tenant_id, fn ($query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->latest('invoice_date')
            ->get();
    }

    public function outstandingSupplierBills(int $supplierId, ?User $user = null): Collection
    {
        return Purchase::query()
            ->where('supplier_id', $supplierId)
            ->when($user?->tenant_id, fn ($query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->latest('purchase_date')
            ->get();
    }

    public function resolveBill(string $billType, int $billId, int $partyId, string $partyType): Model
    {
        if ($billType === 'sales_invoice' && $partyType === 'customer') {
            return SalesInvoice::query()->lockForUpdate()->where('customer_id', $partyId)->findOrFail($billId);
        }

        if ($billType === 'purchase' && $partyType === 'supplier') {
            return Purchase::query()->lockForUpdate()->where('supplier_id', $partyId)->findOrFail($billId);
        }

        throw ValidationException::withMessages([
            'allocations' => 'Selected bill does not belong to the chosen party.',
        ]);
    }

    public function partyExists(string $partyType, int $partyId, User $user): bool
    {
        $query = $partyType === 'customer' ? Customer::query() : Supplier::query();

        return $query
            ->when($user->tenant_id, fn ($builder, $tenantId) => $builder->where('tenant_id', $tenantId))
            ->whereKey($partyId)
            ->exists();
    }
}
