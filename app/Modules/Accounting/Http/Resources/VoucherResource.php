<?php

namespace App\Modules\Accounting\Http\Resources;

use App\Core\Support\MoneyAmount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="VoucherResource",
 *     title="Voucher Resource",
 *     description="PharmaNP Voucher Resource response contract",
 *
 *     @OA\Property(property="id", type="integer", nullable=true, example=1),
 *     @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 * )
 */
class VoucherResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'voucher_no' => $this->voucher_no,
            'voucher_date' => $this->voucher_date?->toDateString(),
            'voucher_type' => $this->voucher_type,
            'voucher_type_label' => str($this->voucher_type)->replace('_', ' ')->title()->toString(),
            'total_amount' => MoneyAmount::decimal($this->total_amount),
            'notes' => $this->notes,
            'entries_count' => (int) ($this->entries_count ?? $this->entries?->count() ?? 0),
            'entries' => $this->whenLoaded('entries', fn () => $this->entries->map(fn ($entry) => [
                'line_no' => $entry->line_no,
                'account_type' => $entry->account_type,
                'party_type' => $entry->party_type,
                'party_id' => $entry->party_id,
                'entry_type' => $entry->entry_type,
                'amount' => MoneyAmount::decimal($entry->amount),
                'notes' => $entry->notes,
            ])->values()),
        ];
    }
}
