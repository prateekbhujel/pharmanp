<?php

namespace App\Modules\Sales\Contracts;

use App\Models\User;
use App\Modules\Sales\Models\SalesInvoice;

interface SalesInvoiceServiceInterface
{
    public function create(array $data, User $user): SalesInvoice;

    public function updatePayment(SalesInvoice $invoice, array $data, User $user): SalesInvoice;
}
