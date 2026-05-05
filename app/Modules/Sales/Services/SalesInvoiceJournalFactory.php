<?php

namespace App\Modules\Sales\Services;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Modules\Sales\Models\SalesInvoice;
use App\Core\Utils\Math;

class SalesInvoiceJournalFactory
{
    /**
     * Generate journal entries for a sales invoice.
     */
    public function make(SalesInvoice $invoice, string $paidAmount, string $cashAccount = 'cash'): array
    {
        $entries = [];
        $grandTotal = (string) $invoice->grand_total;

        if (Math::sub($paidAmount, '0') > 0) {
            $entries[] = [
                'account_type' => $cashAccount,
                'debit' => $paidAmount,
                'credit' => '0.00',
                'notes' => 'Collected on ' . $invoice->invoice_no,
            ];
        }

        $outstanding = Math::sub($grandTotal, $paidAmount);

        if (Math::sub($outstanding, '0') > 0) {
            $entries[] = [
                'account_type' => 'receivable',
                'party_type' => $invoice->customer_id ? 'customer' : null,
                'party_id' => $invoice->customer_id,
                'debit' => $outstanding,
                'credit' => '0.00',
                'notes' => 'Outstanding on ' . $invoice->invoice_no,
            ];
        }

        $entries[] = [
            'account_type' => 'sales',
            'debit' => '0.00',
            'credit' => $grandTotal,
            'notes' => 'Sales ' . $invoice->invoice_no,
        ];

        return $entries;
    }
}
