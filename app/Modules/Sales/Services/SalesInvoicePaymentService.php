<?php

namespace App\Modules\Sales\Services;

use App\Core\Utils\Math;
use App\Models\User;
use App\Modules\Accounting\Services\AccountTransactionPostingService;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Repositories\Interfaces\SalesInvoiceRepositoryInterface;
use Illuminate\Validation\ValidationException;

class SalesInvoicePaymentService
{
    public function __construct(
        private readonly AccountTransactionPostingService $accounts,
        private readonly SalesInvoiceRepositoryInterface $invoices,
        private readonly SalesInvoiceJournalFactory $journals,
    ) {}

    /**
     * Update payment on an existing invoice, update customer balance, and refresh journal entries.
     */
    public function update(SalesInvoice $invoice, array $data, User $user): SalesInvoice
    {
        $paidAmount = Math::round((string) ($data['paid_amount'] ?? 0), 2);
        $grandTotal = (string) $invoice->grand_total;

        if (Math::sub($paidAmount, $grandTotal) > 0) {
            throw ValidationException::withMessages(['paid_amount' => 'Paid amount cannot be greater than invoice total.']);
        }

        $oldDue = Math::sub($grandTotal, (string) $invoice->paid_amount);
        $newDue = Math::sub($grandTotal, $paidAmount);

        $this->invoices->saveInvoice($invoice, [
            'paid_amount' => (float) $paidAmount,
            'payment_mode_id' => $data['payment_mode_id'] ?? $invoice->payment_mode_id,
            'payment_status' => $this->calculateStatus($grandTotal, $paidAmount),
            'updated_by' => $user->id,
        ]);

        if ($invoice->customer_id) {
            $balanceAdjustment = Math::sub($newDue, $oldDue);
            $this->invoices->incrementCustomerBalance((int) $invoice->customer_id, (float) $balanceAdjustment, $user, $invoice);
        }

        $cashAccount = ($data['cash_account'] ?? 'cash') === 'bank' ? 'bank' : 'cash';

        $this->accounts->replaceForSource(
            $user,
            'sales_invoice',
            $invoice->id,
            $invoice->invoice_date->toDateString(),
            $this->journals->make($invoice, $paidAmount, $cashAccount),
        );

        return $this->invoices->fresh($invoice, includeReturns: true);
    }

    private function calculateStatus(string $total, string $paid): string
    {
        if (Math::sub($paid, '0') <= 0) {
            return 'unpaid';
        }

        return Math::sub($paid, $total) >= 0 ? 'paid' : 'partial';
    }
}
