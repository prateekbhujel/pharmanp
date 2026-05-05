<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Modules\Inventory\Models\Company;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\Unit;
use App\Modules\MR\Models\MedicalRepresentative;
use App\Modules\Party\Models\Customer;
use App\Modules\Party\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MrAndTrialBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_posted_purchase_and_sales_feed_trial_balance_and_mr_report(): void
    {
        [$user, $product, $supplier, $customer, $mr] = $this->fixture();

        $this->actingAs($user)->postJson('/api/v1/purchases', [
            'supplier_id' => $supplier->id,
            'purchase_date' => '2026-04-25',
            'paid_amount' => 50,
            'items' => [[
                'product_id' => $product->id,
                'batch_no' => 'TB-001',
                'expires_at' => '2027-04-25',
                'quantity' => 10,
                'purchase_price' => 5,
                'mrp' => 8,
            ]],
        ])->assertCreated();

        $this->actingAs($user)->postJson('/api/v1/mr/visits', [
            'medical_representative_id' => $mr->id,
            'customer_id' => $customer->id,
            'visit_date' => '2026-04-25',
            'status' => 'visited',
            'order_value' => 1200,
        ])->assertCreated();

        $this->actingAs($user)->postJson('/api/v1/sales/invoices', [
            'customer_id' => $customer->id,
            'medical_representative_id' => $mr->id,
            'invoice_date' => '2026-04-25',
            'sale_type' => 'pos',
            'paid_amount' => 8,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_price' => 8,
            ]],
        ])->assertCreated();

        $trialBalance = $this->actingAs($user)->getJson('/api/v1/reports/trial-balance?from=2026-04-01&to=2026-04-30')
            ->assertOk()
            ->assertJsonPath('summary.difference', '0.00')
            ->json('data');

        $this->assertTrue(collect($trialBalance)->contains(fn ($row) => $row['account'] === 'Inventory Stock' && (float) $row['debit'] === 50.0));
        $this->assertTrue(collect($trialBalance)->contains(fn ($row) => $row['account'] === 'Sales Income' && (float) $row['credit'] === 16.0));

        $accountTree = $this->actingAs($user)->getJson('/api/v1/reports/account-tree?from=2026-04-01&to=2026-04-30')
            ->assertOk()
            ->assertJsonPath('summary.debit', '66.00')
            ->assertJsonPath('summary.credit', '66.00')
            ->json('data');

        $this->assertTrue(collect($accountTree)->contains(fn ($row) => $row['account_key'] === 'inventory' && (float) $row['debit'] === 50.0));

        $this->actingAs($user)->getJson('/api/v1/mr/performance?from=2026-04-01&to=2026-04-30')
            ->assertOk()
            ->assertJsonPath('data.rows.0.name', 'Nabin MR')
            ->assertJsonPath('data.totals.visits', 1)
            ->assertJsonPath('data.totals.invoiced_value', 16);
    }

    private function fixture(): array
    {
        Setting::putValue('app.installed', ['installed' => true]);
        $company = Company::query()->create(['name' => 'Trial Pharma']);
        $unit = Unit::query()->create(['company_id' => $company->id, 'name' => 'Piece']);
        $user = User::factory()->create(['company_id' => $company->id, 'is_owner' => true]);
        $product = Product::query()->create([
            'company_id' => $company->id,
            'unit_id' => $unit->id,
            'name' => 'Cetirizine',
            'sku' => 'CTZ-10',
            'mrp' => 8,
            'purchase_price' => 5,
            'selling_price' => 8,
        ]);
        $supplier = Supplier::query()->create(['company_id' => $company->id, 'name' => 'Supplier One']);
        $customer = Customer::query()->create(['company_id' => $company->id, 'name' => 'Customer One']);
        $mr = MedicalRepresentative::query()->create([
            'company_id' => $company->id,
            'name' => 'Nabin MR',
            'monthly_target' => 100000,
            'is_active' => true,
        ]);

        return [$user, $product, $supplier, $customer, $mr];
    }
}
