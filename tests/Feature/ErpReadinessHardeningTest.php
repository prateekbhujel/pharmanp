<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Modules\Accounting\Models\AccountTransaction;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Models\Voucher;
use App\Modules\Accounting\Services\PayableService;
use App\Modules\Accounting\Services\ReceivableService;
use App\Modules\ImportExport\Repositories\Interfaces\ExportRepositoryInterface;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\Company;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Party\Models\Customer;
use App\Modules\Party\Models\Supplier;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Setup\Models\FiscalYear;
use App\Modules\Setup\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ErpReadinessHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_dataset_authorization_and_user_scope_are_enforced(): void
    {
        [$tenantA, $companyA, $storeA] = $this->context('tenant-a');
        [$tenantB, $companyB, $storeB] = $this->context('tenant-b');

        $nonOwner = User::factory()->create([
            'tenant_id' => $tenantA->id,
            'company_id' => $companyA->id,
            'store_id' => $storeA->id,
            'is_owner' => false,
        ]);
        $ownerA = User::factory()->create([
            'tenant_id' => $tenantA->id,
            'company_id' => $companyA->id,
            'store_id' => $storeA->id,
            'is_owner' => true,
            'email' => 'owner-a@example.test',
        ]);
        $userB = User::factory()->create([
            'tenant_id' => $tenantB->id,
            'company_id' => $companyB->id,
            'store_id' => $storeB->id,
            'is_owner' => true,
            'email' => 'owner-b@example.test',
        ]);

        $this->actingAs($nonOwner)->getJson('/api/v1/exports/users/xlsx')->assertForbidden();
        $this->actingAs($nonOwner)->getJson('/api/v1/exports/payments/xlsx')->assertForbidden();

        $rows = $this->exportRows($ownerA, 'users');
        $emails = $rows->pluck('Email')->all();

        $this->assertContains($ownerA->email, $emails);
        $this->assertNotContains($userB->email, $emails);
    }

    public function test_account_tree_export_is_scoped_by_tenant_company_store_and_fiscal_year(): void
    {
        [$tenantA, $companyA, $storeA] = $this->context('tenant-a');
        [$tenantB, $companyB, $storeB] = $this->context('tenant-b');
        $otherStoreA = Store::query()->create(['tenant_id' => $tenantA->id, 'company_id' => $companyA->id, 'name' => 'Other Store']);
        $ownerA = User::factory()->create(['tenant_id' => $tenantA->id, 'company_id' => $companyA->id, 'store_id' => $storeA->id, 'is_owner' => true]);
        $fiscalYearA = FiscalYear::query()->create([
            'tenant_id' => $tenantA->id,
            'company_id' => $companyA->id,
            'name' => 'FY A',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
            'status' => 'active',
        ]);
        $otherFiscalYear = FiscalYear::query()->create([
            'tenant_id' => $tenantA->id,
            'company_id' => $companyA->id,
            'name' => 'FY B',
            'starts_on' => '2027-01-01',
            'ends_on' => '2027-12-31',
            'status' => 'active',
        ]);

        AccountTransaction::query()->create([
            'tenant_id' => $tenantA->id,
            'company_id' => $companyA->id,
            'store_id' => $storeA->id,
            'fiscal_year_id' => $fiscalYearA->id,
            'transaction_date' => '2026-05-05',
            'account_type' => 'cash',
            'source_type' => 'test',
            'source_id' => 1,
            'debit' => 100,
            'credit' => 0,
        ]);
        AccountTransaction::query()->create([
            'tenant_id' => $tenantA->id,
            'company_id' => $companyA->id,
            'store_id' => $storeA->id,
            'fiscal_year_id' => $otherFiscalYear->id,
            'transaction_date' => '2026-05-05',
            'account_type' => 'cash',
            'source_type' => 'test',
            'source_id' => 2,
            'debit' => 200,
            'credit' => 0,
        ]);
        AccountTransaction::query()->create([
            'tenant_id' => $tenantB->id,
            'company_id' => $companyB->id,
            'store_id' => $storeB->id,
            'fiscal_year_id' => $fiscalYearA->id,
            'transaction_date' => '2026-05-05',
            'account_type' => 'cash',
            'source_type' => 'test',
            'source_id' => 3,
            'debit' => 900,
            'credit' => 0,
        ]);
        AccountTransaction::query()->create([
            'tenant_id' => $tenantA->id,
            'company_id' => $companyA->id,
            'store_id' => $otherStoreA->id,
            'fiscal_year_id' => $fiscalYearA->id,
            'transaction_date' => '2026-05-05',
            'account_type' => 'cash',
            'source_type' => 'test',
            'source_id' => 4,
            'debit' => 700,
            'credit' => 0,
        ]);

        $rows = $this->exportRows($ownerA, 'account-tree', ['fiscal_year_id' => $fiscalYearA->id]);
        $cash = $rows->firstWhere('Code', '1100');

        $this->assertSame('100.00', $cash['Debit']);
    }

    public function test_stock_adjustment_cross_context_mutations_are_blocked_before_stock_changes(): void
    {
        [$tenantA, $companyA, $storeA, $unitA] = $this->context('tenant-a');
        [$tenantB, $companyB, $storeB, $unitB] = $this->context('tenant-b');
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'company_id' => $companyA->id, 'store_id' => $storeA->id, 'is_owner' => true]);
        [$productB, $batchB] = $this->productBatch($tenantB, $companyB, $storeB, $unitB, 'Tenant B Product');
        $adjustmentB = $this->stockAdjustment($tenantB, $companyB, $storeB, $productB, $batchB);

        $this->actingAs($userA)
            ->putJson('/api/v1/inventory/stock-adjustments/'.$adjustmentB->id, $this->stockAdjustmentPayload($productB, $batchB))
            ->assertNotFound();

        $this->actingAs($userA)
            ->deleteJson('/api/v1/inventory/stock-adjustments/'.$adjustmentB->id)
            ->assertNotFound();

        $this->assertSame(0, StockMovement::query()->where('source_id', $adjustmentB->id)->count());

        $companyB2 = Company::query()->create(['tenant_id' => $tenantA->id, 'name' => 'Tenant A Second Company']);
        $storeB2 = Store::query()->create(['tenant_id' => $tenantA->id, 'company_id' => $companyB2->id, 'name' => 'Second Store']);
        $unitB2 = Unit::query()->create(['tenant_id' => $tenantA->id, 'company_id' => $companyB2->id, 'name' => 'Box']);
        [$otherCompanyProduct, $otherCompanyBatch] = $this->productBatch($tenantA, $companyB2, $storeB2, $unitB2, 'Other Company Product');
        $otherCompanyAdjustment = $this->stockAdjustment($tenantA, $companyB2, $storeB2, $otherCompanyProduct, $otherCompanyBatch);

        $list = $this->actingAs($userA)
            ->getJson('/api/v1/inventory/stock-adjustments')
            ->assertOk();

        $this->assertNotContains($otherCompanyAdjustment->id, collect($list->json('data'))->pluck('id')->all());

        $this->actingAs($userA)
            ->putJson('/api/v1/inventory/stock-adjustments/'.$otherCompanyAdjustment->id, $this->stockAdjustmentPayload($otherCompanyProduct, $otherCompanyBatch))
            ->assertUnprocessable();

        $this->actingAs($userA)
            ->deleteJson('/api/v1/inventory/stock-adjustments/'.$otherCompanyAdjustment->id)
            ->assertNotFound();

        $this->assertSame(0, StockMovement::query()->where('source_id', $otherCompanyAdjustment->id)->count());
    }

    public function test_invalid_stock_adjustment_batch_product_context_does_not_reverse_existing_stock(): void
    {
        [$tenant, $company, $store, $unit] = $this->context('tenant-a');
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'store_id' => $store->id, 'is_owner' => true]);
        [$product, $batch] = $this->productBatch($tenant, $company, $store, $unit, 'Primary Product');
        [$otherProduct] = $this->productBatch($tenant, $company, $store, $unit, 'Other Product');
        $adjustment = $this->stockAdjustment($tenant, $company, $store, $product, $batch);

        StockMovement::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'store_id' => $store->id,
            'movement_date' => '2026-05-05',
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'movement_type' => 'adjustment_in',
            'quantity_in' => 1,
            'quantity_out' => 0,
            'source_type' => 'stock_adjustment',
            'source_id' => $adjustment->id,
        ]);

        $this->actingAs($user)
            ->putJson('/api/v1/inventory/stock-adjustments/'.$adjustment->id, $this->stockAdjustmentPayload($otherProduct, $batch))
            ->assertUnprocessable();

        $this->assertSame(1, StockMovement::query()->where('source_id', $adjustment->id)->count());
        $this->assertSame('10.000', (string) $batch->fresh()->quantity_available);
    }

    public function test_operational_lists_hide_same_tenant_other_company_or_store_rows(): void
    {
        [$tenant, $companyA, $storeA, $unitA] = $this->context('tenant-a');
        $companyB = Company::query()->create(['tenant_id' => $tenant->id, 'name' => 'Other Company']);
        $storeB = Store::query()->create(['tenant_id' => $tenant->id, 'company_id' => $companyB->id, 'name' => 'Other Store']);
        $unitB = Unit::query()->create(['tenant_id' => $tenant->id, 'company_id' => $companyB->id, 'name' => 'Box']);
        $userA = User::factory()->create(['tenant_id' => $tenant->id, 'company_id' => $companyA->id, 'store_id' => $storeA->id, 'is_owner' => true]);

        $visible = $this->operationalRows($tenant, $companyA, $storeA, 'VISIBLE');
        $hidden = $this->operationalRows($tenant, $companyB, $storeB, 'HIDDEN');
        [$visibleProduct, $visibleBatch] = $this->productBatch($tenant, $companyA, $storeA, $unitA, 'Visible Stock Product');
        [$hiddenProduct, $hiddenBatch] = $this->productBatch($tenant, $companyB, $storeB, $unitB, 'Hidden Stock Product');
        $visibleMovement = StockMovement::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $companyA->id,
            'store_id' => $storeA->id,
            'movement_date' => '2026-05-05',
            'product_id' => $visibleProduct->id,
            'batch_id' => $visibleBatch->id,
            'movement_type' => 'manual_batch_in',
            'quantity_in' => 1,
        ]);
        $hiddenMovement = StockMovement::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $companyB->id,
            'store_id' => $storeB->id,
            'movement_date' => '2026-05-05',
            'product_id' => $hiddenProduct->id,
            'batch_id' => $hiddenBatch->id,
            'movement_type' => 'manual_batch_in',
            'quantity_in' => 1,
        ]);

        $this->assertListContainsOnly('/api/v1/accounting/payments', $userA, $visible['payment']->id, $hidden['payment']->id);
        $this->assertListContainsOnly('/api/v1/accounting/vouchers', $userA, $visible['voucher']->id, $hidden['voucher']->id);
        $this->assertListContainsOnly('/api/v1/sales/invoices', $userA, $visible['invoice']->id, $hidden['invoice']->id);
        $this->assertListContainsOnly('/api/v1/purchases', $userA, $visible['purchase']->id, $hidden['purchase']->id);
        $this->assertListContainsOnly('/api/v1/inventory/stock-movements', $userA, $visibleMovement->id, $hiddenMovement->id);
    }

    public function test_pos_lookup_and_batch_options_only_return_saleable_current_context_batches(): void
    {
        [$tenant, $companyA, $storeA, $unitA] = $this->context('tenant-a');
        $companyB = Company::query()->create(['tenant_id' => $tenant->id, 'name' => 'Other Company']);
        $storeB = Store::query()->create(['tenant_id' => $tenant->id, 'company_id' => $companyB->id, 'name' => 'Other Store']);
        $unitB = Unit::query()->create(['tenant_id' => $tenant->id, 'company_id' => $companyB->id, 'name' => 'Box']);
        $userA = User::factory()->create(['tenant_id' => $tenant->id, 'company_id' => $companyA->id, 'store_id' => $storeA->id, 'is_owner' => true]);

        [$validProduct, $validBatch] = $this->productBatch($tenant, $companyA, $storeA, $unitA, 'Lookup Valid Product');
        [, $expiredBatch] = $this->productBatch($tenant, $companyA, $storeA, $unitA, 'Lookup Expired Product', ['expires_at' => '2026-01-01']);
        [, $inactiveBatch] = $this->productBatch($tenant, $companyA, $storeA, $unitA, 'Lookup Inactive Product', ['is_active' => false]);
        [$otherCompanyProduct] = $this->productBatch($tenant, $companyB, $storeB, $unitB, 'Lookup Other Company Product');

        $lookup = $this->actingAs($userA)->getJson('/api/v1/sales/product-lookup?q=Lookup')->assertOk();
        $ids = collect($lookup->json('data'))->pluck('id')->all();

        $this->assertContains($validProduct->id, $ids);
        $this->assertNotContains($otherCompanyProduct->id, $ids);
        $this->assertCount(1, $lookup->json('data.0.batches'));
        $this->assertSame($validBatch->id, $lookup->json('data.0.batches.0.id'));

        $this->actingAs($userA)
            ->getJson('/api/v1/inventory/batches/options?product_id='.$validProduct->id)
            ->assertOk()
            ->assertJsonPath('data.0.id', $validBatch->id);

        $customer = Customer::query()->create(['tenant_id' => $tenant->id, 'company_id' => $companyA->id, 'name' => 'Retail Customer']);

        foreach ([$expiredBatch, $inactiveBatch] as $batch) {
            $this->actingAs($userA)
                ->postJson('/api/v1/sales/invoices', [
                    'customer_id' => $customer->id,
                    'invoice_date' => '2026-05-05',
                    'sale_type' => 'pos',
                    'paid_amount' => 0,
                    'items' => [[
                        'product_id' => $batch->product_id,
                        'batch_id' => $batch->id,
                        'quantity' => 1,
                        'unit_price' => 10,
                    ]],
                ])
                ->assertUnprocessable();
        }
    }

    public function test_party_balance_mutation_is_scoped_and_credit_balance_is_explicit(): void
    {
        [$tenantA, $companyA] = $this->context('tenant-a');
        [$tenantB, $companyB] = $this->context('tenant-b');
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'company_id' => $companyA->id, 'is_owner' => true]);
        $customerA = Customer::query()->create(['tenant_id' => $tenantA->id, 'company_id' => $companyA->id, 'name' => 'Customer A', 'current_balance' => 10]);
        $customerB = Customer::query()->create(['tenant_id' => $tenantB->id, 'company_id' => $companyB->id, 'name' => 'Customer B', 'current_balance' => 10]);
        $supplierB = Supplier::query()->create(['tenant_id' => $tenantB->id, 'company_id' => $companyB->id, 'name' => 'Supplier B', 'current_balance' => 10]);
        $companyC = Company::query()->create(['tenant_id' => $tenantA->id, 'name' => 'Tenant A Other Company']);
        $customerC = Customer::query()->create(['tenant_id' => $tenantA->id, 'company_id' => $companyC->id, 'name' => 'Customer C', 'current_balance' => 10]);
        $supplierC = Supplier::query()->create(['tenant_id' => $tenantA->id, 'company_id' => $companyC->id, 'name' => 'Supplier C', 'current_balance' => 10]);

        try {
            app(ReceivableService::class)->adjustCustomerBalance($customerB->id, -5, $userA);
            $this->fail('Expected customer balance mutation to be rejected.');
        } catch (ValidationException) {
            $this->assertSame('10.00', (string) $customerB->fresh()->current_balance);
        }

        try {
            app(PayableService::class)->adjustSupplierBalance($supplierB->id, -5, $userA);
            $this->fail('Expected supplier balance mutation to be rejected.');
        } catch (ValidationException) {
            $this->assertSame('10.00', (string) $supplierB->fresh()->current_balance);
        }

        try {
            app(ReceivableService::class)->adjustCustomerBalance($customerC->id, -5, $userA);
            $this->fail('Expected same-tenant other-company customer mutation to be rejected.');
        } catch (ValidationException) {
            $this->assertSame('10.00', (string) $customerC->fresh()->current_balance);
        }

        try {
            app(PayableService::class)->adjustSupplierBalance($supplierC->id, -5, $userA);
            $this->fail('Expected same-tenant other-company supplier mutation to be rejected.');
        } catch (ValidationException) {
            $this->assertSame('10.00', (string) $supplierC->fresh()->current_balance);
        }

        app(ReceivableService::class)->adjustCustomerBalance($customerA->id, -15, $userA);
        $this->assertSame('-5.00', (string) $customerA->fresh()->current_balance);

        $customerA->forceFill(['current_balance' => '0.10'])->save();
        app(ReceivableService::class)->adjustCustomerBalance($customerA->id, 0.20, $userA);
        $this->assertSame('0.30', (string) $customerA->fresh()->current_balance);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Setting::putValue('app.installed', ['installed' => true]);
    }

    private function context(string $slug): array
    {
        $tenant = Tenant::query()->create(['name' => str($slug)->title()->toString(), 'slug' => $slug]);
        $company = Company::query()->create(['tenant_id' => $tenant->id, 'name' => str($slug)->title()->append(' Pharma')->toString()]);
        $store = Store::query()->create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'name' => 'Main Store']);
        $unit = Unit::query()->create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'name' => 'Pcs']);

        return [$tenant, $company, $store, $unit];
    }

    private function exportRows(User $user, string $dataset, array $query = [])
    {
        $request = Request::create('/api/v1/exports/'.$dataset.'/xlsx', 'GET', $query);
        $request->setUserResolver(fn () => $user);

        return app(ExportRepositoryInterface::class)->datasetRows($request, $dataset);
    }

    private function productBatch(Tenant $tenant, Company $company, Store $store, Unit $unit, string $name, array $batchOverrides = []): array
    {
        $product = Product::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'store_id' => $store->id,
            'unit_id' => $unit->id,
            'name' => $name,
            'sku' => str($name)->slug('-')->upper()->limit(30, '')->toString(),
            'mrp' => 10,
            'purchase_price' => 5,
            'selling_price' => 10,
            'is_active' => true,
        ]);

        $batch = Batch::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'store_id' => $store->id,
            'product_id' => $product->id,
            'batch_no' => 'B-'.$product->id,
            'expires_at' => '2027-05-05',
            'quantity_received' => 10,
            'quantity_available' => 10,
            'purchase_price' => 5,
            'mrp' => 10,
            'is_active' => true,
        ], $batchOverrides));

        return [$product, $batch];
    }

    private function stockAdjustment(Tenant $tenant, Company $company, Store $store, Product $product, Batch $batch): StockAdjustment
    {
        return StockAdjustment::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'store_id' => $store->id,
            'adjustment_date' => '2026-05-05',
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'adjustment_type' => 'add',
            'quantity' => 1,
        ]);
    }

    private function stockAdjustmentPayload(Product $product, Batch $batch): array
    {
        return [
            'adjustment_date' => '2026-05-05',
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'adjustment_type' => 'add',
            'quantity' => 1,
            'reason' => 'Count correction',
        ];
    }

    private function operationalRows(Tenant $tenant, Company $company, Store $store, string $prefix): array
    {
        $customer = Customer::query()->create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'name' => $prefix.' Customer']);
        $supplier = Supplier::query()->create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'name' => $prefix.' Supplier']);

        return [
            'payment' => Payment::query()->create([
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'store_id' => $store->id,
                'payment_no' => $prefix.'-PAY',
                'payment_date' => '2026-05-05',
                'direction' => 'in',
                'party_type' => 'customer',
                'party_id' => $customer->id,
                'payment_mode' => 'cash',
                'amount' => 100,
            ]),
            'voucher' => Voucher::query()->create([
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'voucher_no' => $prefix.'-VCH',
                'voucher_date' => '2026-05-05',
                'voucher_type' => 'journal',
                'total_amount' => 100,
            ]),
            'invoice' => SalesInvoice::query()->create([
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'store_id' => $store->id,
                'customer_id' => $customer->id,
                'invoice_no' => $prefix.'-SI',
                'invoice_date' => '2026-05-05',
                'sale_type' => 'retail',
                'status' => 'confirmed',
                'payment_status' => 'unpaid',
                'grand_total' => 100,
                'paid_amount' => 0,
            ]),
            'purchase' => Purchase::query()->create([
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'store_id' => $store->id,
                'supplier_id' => $supplier->id,
                'purchase_no' => $prefix.'-PUR',
                'purchase_date' => '2026-05-05',
                'payment_status' => 'unpaid',
                'grand_total' => 100,
                'paid_amount' => 0,
            ]),
        ];
    }

    private function assertListContainsOnly(string $url, User $user, int $visibleId, int $hiddenId): void
    {
        $response = $this->actingAs($user)->getJson($url)->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($visibleId, $ids);
        $this->assertNotContains($hiddenId, $ids);
    }
}
