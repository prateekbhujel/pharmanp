<?php

namespace App\Modules\Accounting\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoucherResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'voucher_no' => $this->voucher_no,
            'voucher_date' => $this->voucher_date?->toDateString(),
            'voucher_type' => $this->voucher_type,
            'total_amount' => (float) $this->total_amount,
            'notes' => $this->notes,
            'entries' => $this->whenLoaded('entries', fn () => $this->entries->map(fn ($entry) => [
                'line_no' => $entry->line_no,
                'account_type' => $entry->account_type,
                'party_type' => $entry->party_type,
                'party_id' => $entry->party_id,
                'entry_type' => $entry->entry_type,
                'amount' => (float) $entry->amount,
                'notes' => $entry->notes,
            ])->values()),
        ];
    }
}
