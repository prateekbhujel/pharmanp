<?php

namespace App\Modules\Accounting\Contracts;

use App\Models\User;
use App\Modules\Accounting\Models\Voucher;

interface VoucherServiceInterface
{
    public function create(array $data, User $user): Voucher;

    public function update(Voucher $voucher, array $data, User $user): Voucher;

    public function delete(Voucher $voucher): void;
}
