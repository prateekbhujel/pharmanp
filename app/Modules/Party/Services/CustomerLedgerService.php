<?php

namespace App\Modules\Party\Services;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Core\Security\TenantRecordScope;
use App\Models\User;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Party\Models\Customer;
use App\Modules\Party\Repositories\Interfaces\CustomerLedgerRepositoryInterface;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesReturn;

class CustomerLedgerService
{
    public function __construct(
        private readonly CustomerLedgerRepositoryInterface $ledger,
        private readonly TenantRecordScope $scope,
    ) {}

    public function payload(Customer $customer, User $user, ?string $from = null, ?string $to = null): array
    {
        abort_unless($this->scope->canAccess($user, $customer), 404);

        $totals = $this->ledger->totals($customer, $user);

        return [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'address' => $customer->address,
                'current_balance' => round((float) $customer->current_balance, 2),
            ],
            'invoices' => $this->ledger->invoices($customer, $from, $to, $user)->map(fn (SalesInvoice $invoice): array => [
                'id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no,
                'date' => $invoice->invoice_date->format('M j, Y'),
                'grand_total' => round((float) $invoice->grand_total, 2),
                'paid_amount' => round((float) $invoice->paid_amount, 2),
                'due' => round(max(0, (float) $invoice->grand_total - (float) $invoice->paid_amount), 2),
                'payment_status' => $invoice->payment_status,
            ]),
            'returns' => $this->ledger->returns($customer, $from, $to, $user)->map(fn (SalesReturn $return): array => [
                'id' => $return->id,
                'return_no' => $return->return_no,
                'date' => $return->return_date->format('M j, Y'),
                'total_amount' => round((float) $return->total_amount, 2),
                'invoice_no' => $return->invoice?->invoice_no ?? '-',
            ]),
            'payments' => $this->ledger->payments($customer, $from, $to, $user)->map(fn (Payment $payment): array => [
                'id' => $payment->id,
                'payment_no' => $payment->payment_no,
                'date' => $payment->payment_date->format('M j, Y'),
                'direction' => $payment->direction,
                'amount' => round((float) $payment->amount, 2),
                'payment_mode' => $payment->payment_mode,
            ]),
            'summary' => [
                'total_invoiced' => round((float) $totals->total_invoiced, 2),
                'total_returned' => round((float) $totals->total_returned, 2),
                'total_paid' => round((float) $totals->total_paid, 2),
                'total_payments' => round((float) $totals->total_payments, 2),
                'balance' => round((float) $customer->current_balance, 2),
            ],
            'filters' => [
                'from' => $from,
                'to' => $to,
            ],
        ];
    }
}
