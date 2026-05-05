<?php

namespace App\Modules\Setup\Services;

use App\Core\Support\AssetUrl;
use App\Modules\Setup\DTOs\DropdownOptionData;
use App\Modules\Setup\Models\DropdownOption;
use App\Modules\Setup\Repositories\Interfaces\DropdownOptionRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DropdownOptionService
{
    public function __construct(private readonly DropdownOptionRepositoryInterface $options) {}

    public function managed(?string $alias = null): Collection
    {
        return $this->options->managed($alias);
    }

    public function aliases(): array
    {
        return DropdownOption::managedAliases();
    }

    public function create(DropdownOptionData $data, ?UploadedFile $qrFile = null): DropdownOption
    {
        return $this->options->create($this->payload($data, $qrFile));
    }

    public function update(DropdownOption $option, DropdownOptionData $data, ?UploadedFile $qrFile = null): DropdownOption
    {
        return $this->options->update($option, $this->payload($data, $qrFile, $option));
    }

    public function updateStatus(DropdownOption $option, bool $active): DropdownOption
    {
        return $this->options->updateStatus($option, $active);
    }

    public function delete(DropdownOption $option): void
    {
        if ($this->options->linkedUsageCount($option) > 0) {
            throw ValidationException::withMessages([
                'dropdown_option' => 'This option is already in use and cannot be deleted.',
            ]);
        }

        $this->options->delete($option);
    }

    private function payload(DropdownOptionData $data, ?UploadedFile $qrFile = null, ?DropdownOption $option = null): array
    {
        $payload = $data->toArray();
        $meta = $payload['meta'];
        $payload['status'] = (int) ($data->status ?? $option?->status ?? true);

        if ($qrFile) {
            $meta['qr_url'] = AssetUrl::publicStorage($qrFile->store('settings/payment-modes', 'public'));
        } elseif ($option?->meta && isset($option->meta['qr_url']) && ! array_key_exists('qr_url', $meta)) {
            $meta['qr_url'] = $option->meta['qr_url'];
        }

        $payload['meta'] = $meta;

        return $payload;
    }
}
