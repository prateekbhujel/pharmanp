<?php

namespace App\Modules\Inventory\DTOs;

final readonly class ProductData
{
    public function __construct(
        public string $name,
        public ?string $sku = null,
        public ?string $barcode = null,
        public ?string $productCode = null,
        public ?int $companyId = null,
        public ?int $storeId = null,
        public ?int $categoryId = null,
        public ?int $manufacturerId = null,
        public ?int $unitId = null,
        public float $conversion = 1,
        public ?string $genericName = null,
        public ?string $composition = null,
        public ?string $groupName = null,
        public ?string $manufacturerName = null,
        public ?string $formulation = null,
        public ?string $strength = null,
        public ?string $rackLocation = null,
        public float $previousPrice = 0,
        public float $mrp = 0,
        public float $purchasePrice = 0,
        public float $sellingPrice = 0,
        public float $ccRate = 0,
        public float $discountPercent = 0,
        public int $reorderLevel = 10,
        public int $reorderQuantity = 0,
        public bool $isBatchTracked = true,
        public bool $isActive = true,
        public ?string $notes = null,
        public ?string $keywords = null,
        public ?string $description = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            sku: $data['sku'] ?? null,
            barcode: $data['barcode'] ?? null,
            productCode: $data['product_code'] ?? null,
            companyId: isset($data['company_id']) ? (int) $data['company_id'] : null,
            storeId: isset($data['store_id']) ? (int) $data['store_id'] : null,
            categoryId: isset($data['category_id']) ? (int) $data['category_id'] : null,
            manufacturerId: isset($data['manufacturer_id']) ? (int) $data['manufacturer_id'] : null,
            unitId: isset($data['unit_id']) ? (int) $data['unit_id'] : null,
            conversion: (float) ($data['conversion'] ?? 1),
            genericName: $data['generic_name'] ?? null,
            composition: $data['composition'] ?? null,
            groupName: $data['group_name'] ?? null,
            manufacturerName: $data['manufacturer_name'] ?? null,
            formulation: $data['formulation'] ?? null,
            strength: $data['strength'] ?? null,
            rackLocation: $data['rack_location'] ?? null,
            previousPrice: (float) ($data['previous_price'] ?? 0),
            mrp: (float) ($data['mrp'] ?? 0),
            purchasePrice: (float) ($data['purchase_price'] ?? 0),
            sellingPrice: (float) ($data['selling_price'] ?? 0),
            ccRate: (float) ($data['cc_rate'] ?? 0),
            discountPercent: (float) ($data['discount_percent'] ?? 0),
            reorderLevel: (int) ($data['reorder_level'] ?? 10),
            reorderQuantity: (int) ($data['reorder_quantity'] ?? 0),
            isBatchTracked: self::booleanValue($data['is_batch_tracked'] ?? true),
            isActive: self::booleanValue($data['is_active'] ?? true),
            notes: $data['notes'] ?? null,
            keywords: $data['keywords'] ?? null,
            description: $data['description'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'product_code' => $this->productCode,
            'company_id' => $this->companyId,
            'store_id' => $this->storeId,
            'category_id' => $this->categoryId,
            'manufacturer_id' => $this->manufacturerId,
            'unit_id' => $this->unitId,
            'conversion' => $this->conversion,
            'generic_name' => $this->genericName,
            'composition' => $this->composition,
            'group_name' => $this->groupName,
            'manufacturer_name' => $this->manufacturerName,
            'formulation' => $this->formulation,
            'strength' => $this->strength,
            'rack_location' => $this->rackLocation,
            'previous_price' => $this->previousPrice,
            'mrp' => $this->mrp,
            'purchase_price' => $this->purchasePrice,
            'selling_price' => $this->sellingPrice,
            'cc_rate' => $this->ccRate,
            'discount_percent' => $this->discountPercent,
            'reorder_level' => $this->reorderLevel,
            'reorder_quantity' => $this->reorderQuantity,
            'is_batch_tracked' => $this->isBatchTracked,
            'is_active' => $this->isActive,
            'notes' => $this->notes,
            'keywords' => $this->keywords,
            'description' => $this->description,
        ];
    }

    private static function booleanValue(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
    }
}
