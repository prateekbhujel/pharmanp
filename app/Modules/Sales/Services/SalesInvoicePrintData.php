<?php

namespace App\Modules\Sales\Services;

use App\Models\Setting;
use App\Modules\Sales\Models\SalesInvoice;

class SalesInvoicePrintData
{
    /**
     * Build the payload required for printing a sales invoice.
     */
    public function get(SalesInvoice $invoice): array
    {
        return [
            'invoice' => $invoice->load(['customer', 'medicalRepresentative', 'paymentMode', 'items.product', 'items.batch']),
            'branding' => Setting::getValue('app.branding', ['app_name' => 'PharmaNP']),
        ];
    }
}
