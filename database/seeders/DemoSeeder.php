<?php

namespace Database\Seeders;

use App\Core\Services\DocumentNumberService;
use App\Core\Services\InstallationService;
use App\Models\Setting;
use App\Models\User;
use App\Modules\Accounting\Models\AccountTransaction;
use App\Modules\Accounting\Models\Expense;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Services\VoucherService;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\Company;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\ProductCategory;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Inventory\Services\StockAdjustmentService;
use App\Modules\MR\Models\Branch;
use App\Modules\MR\Models\MedicalRepresentative;
use App\Modules\MR\Models\RepresentativeVisit;
use App\Modules\Party\Models\Customer;
use App\Modules\Party\Models\Supplier;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Services\PurchaseEntryService;
use App\Modules\Purchase\Services\PurchaseOrderService;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Services\SalesInvoiceService;
use App\Modules\Setup\Models\DropdownOption;
use App\Modules\Setup\Models\FiscalYear;
use App\Modules\Setup\Models\PartyType;
use App\Modules\Setup\Models\SupplierType;
use App\Modules\Setup\Models\Tenant;
use App\Modules\Setup\Services\AccessControlService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class DemoSeeder extends Seeder
{
    private const VERSION = '2026-04-29-realistic-v3';

    private Carbon $today;

    private array $firstNames = [
        'Pratik', 'Ranjan', 'Alika', 'Sampanna', 'Dharma', 'Gokul', 'Abhisek', 'Mandip',
        'Srijal', 'Utakrsha', 'Khush', 'Ryan', 'Sonu', 'Smriti', 'Pooja',
    ];

    private array $lastNames = [
        'Bhujel', 'Dangol', 'Shrestha', 'Maharjan', 'Rimal', 'Gumaju', 'Adhikari',
        'Subedi', 'Aryal', 'Dungana',
    ];

    private array $places = [
        'Kathmandu', 'Pokhara', 'Patan', 'Bhaktapur', 'Baneshwor', 'Maharajgunj',
        'New Road', 'Thamel', 'Kalanki', 'Koteshwor', 'Chabahil', 'Boudha',
        'Lakeside Pokhara', 'Prithvi Chowk', 'Srijana Chowk',
    ];

    public function __construct()
    {
        $this->today = Carbon::today();
    }

    public function run(): void
    {
        if (Setting::getValue('demo.seeded_version') === self::VERSION) {
            $this->command?->info('Demo data already seeded for '.self::VERSION.'. Run migrate:fresh before reseeding.');

            return;
        }

        DB::disableQueryLog();

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
        }

        $context = DB::transaction(fn () => $this->seedFoundation());

        $this->seedProducts($context);
        $this->seedRepresentatives($context);
        $this->seedPurchaseOrdersAndEntries($context);
        $this->seedSalesInvoices($context);
        $this->seedReturnsAndAdjustments($context);
        $this->seedPaymentsExpensesAndVouchers($context);
        $this->seedVisits($context);

        app(InstallationService::class)->markInstalled([
            'tenant_id' => $context['tenant']->id,
            'company_id' => $context['company']->id,
            'store_id' => $context['store']->id,
            'seeded_demo' => true,
        ]);

        Setting::putValue('demo.seeded_version', self::VERSION, true);

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON');
        }

        $this->command?->info('Realistic PharmaNP demo seeded. Login: pratik@admin.com / password');
    }

    private function seedFoundation(): array
    {
        $tenant = Tenant::query()->withTrashed()->firstOrCreate(
            ['slug' => 'pharmanp-demo'],
            [
                'name' => 'Kathmandu Care Pharmacy',
                'status' => 'active',
                'plan_code' => 'standalone',
            ],
        );

        if ($tenant->trashed()) {
            $tenant->restore();
        }

        $company = Company::query()->withTrashed()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Kathmandu Care Pharmacy'],
            [
                'legal_name' => 'Kathmandu Care Pharmacy Pvt. Ltd.',
                'pan_number' => '609887421',
                'phone' => '01-5421188',
                'email' => 'care@kathmanducarepharmacy.test',
                'address' => 'Baneshwor, Kathmandu',
                'country' => 'NP',
                'company_type' => 'pharmacy',
                'default_cc_rate' => 0,
                'is_active' => true,
            ],
        );
        $company->restore();

        $store = Store::query()->withTrashed()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'company_id' => $company->id, 'code' => 'KTM-MAIN'],
            [
                'name' => 'Kathmandu Main Store',
                'phone' => '01-5421188',
                'address' => 'Baneshwor, Kathmandu',
                'is_default' => true,
                'is_active' => true,
            ],
        );
        $store->restore();

        $branches = collect([
            ['code' => 'KTM-HQ', 'name' => 'Kathmandu Head Office', 'type' => 'hq', 'address' => 'Baneshwor, Kathmandu'],
            ['code' => 'PKR-BR', 'name' => 'Pokhara Branch', 'type' => 'branch', 'address' => 'Lakeside, Pokhara'],
            ['code' => 'PTN-BR', 'name' => 'Patan Counter', 'type' => 'branch', 'address' => 'Pulchowk, Patan'],
        ])->map(function (array $row) use ($tenant, $company, $store) {
            $branch = Branch::query()->withTrashed()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $row['code']],
                [
                    'company_id' => $company->id,
                    'store_id' => $store->id,
                    'name' => $row['name'],
                    'type' => $row['type'],
                    'parent_id' => null,
                    'address' => $row['address'],
                    'phone' => '98'.random_int(41000000, 98999999),
                    'is_active' => true,
                ],
            );
            $branch->restore();

            return $branch;
        })->values();

        $owner = User::query()->updateOrCreate(
            ['email' => 'pratik@admin.com'],
            [
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'store_id' => $store->id,
                'branch_id' => $branches->first()->id,
                'name' => 'Pratik Admin',
                'phone' => '9841000001',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_owner' => true,
                'is_active' => true,
            ],
        );

        $this->seedAccessControl($owner);
        $this->seedSettings($tenant, $company, $store);

        $supplierTypes = $this->seedSupplierTypes();
        $partyTypes = $this->seedPartyTypes();
        $dropdowns = $this->seedDropdowns();
        $fiscalYear = $this->seedFiscalYear($tenant, $company, $owner);
        $manufacturers = $this->seedManufacturers($tenant, $owner);
        $units = $this->seedUnits($tenant, $company, $owner);
        $categories = $this->seedCategories($tenant, $company, $owner);
        $suppliers = $this->seedSuppliers($tenant, $company, $owner, $supplierTypes);
        $customers = $this->seedCustomers($tenant, $company, $owner, $partyTypes);

        return compact(
            'tenant',
            'company',
            'store',
            'branches',
            'owner',
            'supplierTypes',
            'partyTypes',
            'dropdowns',
            'fiscalYear',
            'manufacturers',
            'units',
            'categories',
            'suppliers',
            'customers',
        );
    }

    private function seedAccessControl(User $owner): void
    {
        $access = app(AccessControlService::class);
        $access->syncPermissions();

        $ownerRole = Role::query()->firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        $ownerRole->syncPermissions($access->permissionNames());
        $owner->assignRole($ownerRole);

        Role::query()->firstOrCreate(['name' => 'Pharmacy Manager', 'guard_name' => 'web'])
            ->syncPermissions(array_filter($access->permissionNames(), fn (string $permission) => ! Str::startsWith($permission, ['roles.', 'system.'])));

        Role::query()->firstOrCreate(['name' => 'MR', 'guard_name' => 'web'])
            ->syncPermissions(['dashboard.view', 'mr.view', 'mr.visits.manage']);
    }

    private function seedSettings(Tenant $tenant, Company $company, Store $store): void
    {
        Setting::putValue('app.branding', [
            'app_name' => 'Kathmandu Care Pharmacy',
            'logo_url' => null,
            'sidebar_logo_url' => null,
            'app_icon_url' => null,
            'favicon_url' => null,
            'accent_color' => '#0f766e',
            'layout' => 'vertical',
            'sidebar_default_collapsed' => true,
            'show_breadcrumbs' => true,
            'country_code' => 'NP',
            'currency_symbol' => 'Rs.',
            'calendar_type' => 'bs',
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'store_id' => $store->id,
        ]);

        Setting::putValue('company_email', 'care@kathmanducarepharmacy.test');
        Setting::putValue('company_phone', '01-5421188');
        Setting::putValue('company_address', 'Baneshwor, Kathmandu');
        Setting::putValue('currency_symbol', 'Rs.');
        Setting::putValue('low_stock_threshold', 20);
        Setting::putValue('notification_email', 'admin@kathmanducarepharmacy.test');
        Setting::putValue('mail_from_address', 'noreply@kathmanducarepharmacy.test');
        Setting::putValue('mail_from_name', 'Kathmandu Care Pharmacy');
        Setting::putValue(DocumentNumberService::SETTING_KEY, DocumentNumberService::defaults());
    }

    private function seedSupplierTypes(): array
    {
        return collect([
            ['name' => 'Distributor', 'code' => 'distributor'],
            ['name' => 'Manufacturer Direct', 'code' => 'manufacturer'],
            ['name' => 'Institutional Supplier', 'code' => 'institution'],
            ['name' => 'Local Vendor', 'code' => 'local'],
        ])->mapWithKeys(fn (array $row) => [
            $row['code'] => SupplierType::query()->updateOrCreate(['code' => $row['code']], ['name' => $row['name']]),
        ])->all();
    }

    private function seedPartyTypes(): array
    {
        return collect([
            ['name' => 'Walk-in Customer', 'code' => 'walk_in'],
            ['name' => 'Retail Customer', 'code' => 'retail'],
            ['name' => 'Wholesale Pharmacy', 'code' => 'wholesale'],
            ['name' => 'Clinic / Hospital', 'code' => 'institution'],
        ])->mapWithKeys(fn (array $row) => [
            $row['code'] => PartyType::query()->updateOrCreate(['code' => $row['code']], ['name' => $row['name']]),
        ])->all();
    }

    private function seedDropdowns(): array
    {
        $rows = [
            ['payment_mode', 'Cash', 'cash', ['settlement' => 'instant']],
            ['payment_mode', 'Bank Transfer', 'bank', ['settlement' => 'bank']],
            ['payment_mode', 'Cheque', 'bank', ['settlement' => 'pending']],
            ['payment_mode', 'FonePay QR', 'bank', ['qr_supported' => true]],
            ['payment_mode', 'eSewa Wallet', 'bank', ['qr_supported' => true]],
            ['payment_type', 'Receipt', null, []],
            ['payment_type', 'Payment', null, []],
            ['expense_category', 'Rent', 'operating', []],
            ['expense_category', 'Utilities', 'operating', []],
            ['expense_category', 'Salary', 'payroll', []],
            ['expense_category', 'Delivery & Logistics', 'operating', []],
            ['expense_category', 'License Renewal', 'compliance', []],
            ['adjustment_type', 'add', 'in', []],
            ['adjustment_type', 'subtract', 'out', []],
            ['adjustment_type', 'damaged', 'out', []],
            ['adjustment_type', 'expired', 'out', []],
            ['adjustment_type', 'return', 'in', []],
            ['formulation', 'Tablet', null, []],
            ['formulation', 'Capsule', null, []],
            ['formulation', 'Syrup', null, []],
            ['formulation', 'Injection', null, []],
            ['formulation', 'Ointment', null, []],
            ['formulation', 'Drops', null, []],
            ['sales_type', 'Retail', null, []],
            ['sales_type', 'Wholesale', null, []],
            ['sales_type', 'POS', null, []],
        ];

        return collect($rows)->mapWithKeys(function (array $row) {
            [$alias, $name, $data, $meta] = $row;

            $option = DropdownOption::query()->updateOrCreate(
                ['alias' => $alias, 'name' => $name],
                ['data' => $data, 'meta' => $meta, 'status' => true],
            );

            return [$alias.'.'.$name => $option];
        })->all();
    }

    private function seedFiscalYear(Tenant $tenant, Company $company, User $owner): FiscalYear
    {
        FiscalYear::query()
            ->where('tenant_id', $tenant->id)
            ->where('company_id', $company->id)
            ->update(['is_current' => false]);

        $fiscalYear = FiscalYear::query()->withTrashed()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'company_id' => $company->id, 'name' => '2083/84'],
            [
                'starts_on' => '2026-04-14',
                'ends_on' => '2027-04-13',
                'is_current' => true,
                'status' => 'open',
                'created_by' => $owner->id,
                'updated_by' => $owner->id,
            ],
        );
        $fiscalYear->restore();

        return $fiscalYear;
    }

    private function seedManufacturers(Tenant $tenant, User $owner): array
    {
        $names = [
            'Deurali-Janta Pharmaceuticals', 'Lomus Pharmaceuticals', 'National Healthcare',
            'Asian Pharmaceuticals', 'Quest Pharmaceuticals', 'Nepal Pharmaceuticals Laboratory',
            'Magnus Pharma', 'Everest Parenterals', 'Sun Pharma Nepal', 'Himalayan Medicare',
            'Midas Pharma Trading', 'Pokhara Remedies',
        ];

        return collect($names)->mapWithKeys(function (string $name, int $index) use ($tenant, $owner) {
            $manufacturer = Company::query()->withTrashed()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $name],
                [
                    'legal_name' => $name.' Pvt. Ltd.',
                    'pan_number' => (string) (600000000 + $index * 9137),
                    'phone' => '01-'.(4000000 + $index * 731),
                    'email' => Str::slug($name, '.').'@example.test',
                    'address' => $this->places[$index % count($this->places)],
                    'country' => 'NP',
                    'company_type' => $index % 3 === 0 ? 'domestic' : 'manufacturer',
                    'default_cc_rate' => [0, 1.5, 2, 2.5][$index % 4],
                    'is_active' => true,
                    'created_by' => $owner->id,
                    'updated_by' => $owner->id,
                ],
            );
            $manufacturer->restore();

            return [$name => $manufacturer];
        })->all();
    }

    private function seedUnits(Tenant $tenant, Company $company, User $owner): array
    {
        return collect([
            ['Piece', 'PCS', 'both', 1],
            ['Strip', 'STRIP', 'sales', 10],
            ['Box', 'BOX', 'purchase', 100],
            ['Bottle', 'BTL', 'both', 1],
            ['Vial', 'VIAL', 'both', 1],
            ['Tube', 'TUBE', 'both', 1],
            ['Sachet', 'SACHET', 'both', 1],
        ])->mapWithKeys(function (array $row) use ($tenant, $company, $owner) {
            [$name, $code, $type, $factor] = $row;

            $unit = Unit::query()->withTrashed()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'company_id' => $company->id, 'code' => $code],
                [
                    'name' => $name,
                    'type' => $type,
                    'factor' => $factor,
                    'is_active' => true,
                    'created_by' => $owner->id,
                    'updated_by' => $owner->id,
                ],
            );
            $unit->restore();

            return [$name => $unit];
        })->all();
    }

    private function seedCategories(Tenant $tenant, Company $company, User $owner): array
    {
        return collect([
            'Analgesic', 'Antibiotic', 'Antidiabetic', 'Cardiac', 'GI', 'Respiratory',
            'Supplement', 'Antiseptic', 'Dermatology', 'IV Fluid', 'Eye / ENT', 'Emergency',
        ])->mapWithKeys(function (string $name, int $index) use ($tenant, $company, $owner) {
            $category = ProductCategory::query()->withTrashed()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'company_id' => $company->id, 'name' => $name],
                [
                    'code' => strtoupper(Str::slug($name, '')),
                    'is_active' => true,
                    'created_by' => $owner->id,
                    'updated_by' => $owner->id,
                ],
            );
            $category->restore();

            return [$name => $category];
        })->all();
    }

    private function seedSuppliers(Tenant $tenant, Company $company, User $owner, array $types): array
    {
        $names = [
            'Annapurna Pharma Distributors', 'Valley Medico Concern', 'Pokhara Surgical Suppliers',
            'Himalayan Drug House', 'Patan Pharma Link', 'Boudha Healthcare Traders',
            'Kaski Medical Suppliers', 'New Road Medicine Concern', 'Maharajgunj Pharma Depot',
            'Bir Hospital Supplier Center', 'Thamel Medical Logistics', 'Chabahil Drug Distributors',
            'Lakeside Healthcare Supply', 'Koteshwor Medilink', 'Srijana Medicine House',
            'Kathmandu Vaccine Store', 'Pulchowk Health Traders', 'Nepal Generic Suppliers',
        ];

        return collect($names)->map(function (string $name, int $index) use ($tenant, $company, $owner, $types) {
            $contact = $this->personName($index + 3);
            $supplier = Supplier::query()->withTrashed()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'company_id' => $company->id, 'name' => $name],
                [
                    'supplier_type_id' => array_values($types)[$index % count($types)]->id,
                    'contact_person' => $contact,
                    'phone' => '98'.(41000000 + $index * 5391),
                    'email' => Str::slug($name, '.').'@supplier.test',
                    'pan_number' => (string) (601100000 + $index * 2197),
                    'address' => $this->places[$index % count($this->places)],
                    'opening_balance' => 0,
                    'current_balance' => 0,
                    'is_active' => true,
                    'created_by' => $owner->id,
                    'updated_by' => $owner->id,
                ],
            );
            $supplier->restore();

            return $supplier;
        })->values()->all();
    }

    private function seedCustomers(Tenant $tenant, Company $company, User $owner, array $partyTypes): array
    {
        $customers = collect([[
            'name' => 'Walk-in Customer',
            'place' => 'Kathmandu',
            'type' => 'walk_in',
        ]]);

        for ($i = 0; $i < 84; $i++) {
            $place = $this->places[$i % count($this->places)];
            $suffix = match ($i % 5) {
                0 => 'Pharmacy',
                1 => 'Medico',
                2 => 'Health Clinic',
                3 => 'Drug Store',
                default => 'Community Pharmacy',
            };

            $customers->push([
                'name' => $this->personName($i).' '.$place.' '.$suffix.' '.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                'place' => $place,
                'type' => $i % 4 === 0 ? 'institution' : ($i % 3 === 0 ? 'wholesale' : 'retail'),
            ]);
        }

        return $customers->map(function (array $row, int $index) use ($tenant, $company, $owner, $partyTypes) {
            $customer = Customer::query()->withTrashed()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'company_id' => $company->id, 'name' => $row['name']],
                [
                    'party_type_id' => $partyTypes[$row['type']]?->id ?? null,
                    'contact_person' => $row['type'] === 'walk_in' ? 'Counter Sale' : $this->personName($index),
                    'phone' => $row['type'] === 'walk_in' ? null : '98'.(51000000 + $index * 3821),
                    'email' => $row['type'] === 'walk_in' ? null : Str::slug($row['name'], '.').'@customer.test',
                    'pan_number' => $row['type'] === 'walk_in' ? null : (string) (602200000 + $index * 1741),
                    'address' => $row['place'],
                    'credit_limit' => $row['type'] === 'walk_in' ? 0 : [15000, 25000, 50000, 75000][$index % 4],
                    'opening_balance' => 0,
                    'current_balance' => 0,
                    'is_active' => true,
                    'created_by' => $owner->id,
                    'updated_by' => $owner->id,
                ],
            );
            $customer->restore();

            return $customer;
        })->values()->all();
    }

    private function seedProducts(array &$context): void
    {
        $catalog = $this->productCatalog();
        $manufacturers = array_values($context['manufacturers']);
        $units = $context['units'];
        $categories = $context['categories'];
        $products = [];

        foreach ($catalog as $index => $item) {
            $manufacturer = $manufacturers[$index % count($manufacturers)];
            $unit = $this->unitForFormulation($item['formulation'], $units);
            $category = $categories[$item['category']] ?? $categories['Supplement'];

            $product = Product::query()->withTrashed()->updateOrCreate(
                ['tenant_id' => $context['tenant']->id, 'sku' => $item['sku']],
                [
                    'company_id' => $context['company']->id,
                    'store_id' => $context['store']->id,
                    'category_id' => $category->id,
                    'manufacturer_id' => $manufacturer->id,
                    'unit_id' => $unit->id,
                    'barcode' => 'NP'.str_pad((string) ($index + 1), 11, '0', STR_PAD_LEFT),
                    'product_code' => $item['sku'],
                    'name' => $item['name'],
                    'generic_name' => $item['generic'],
                    'composition' => $item['composition'],
                    'group_name' => $item['category'],
                    'formulation' => $item['formulation'],
                    'strength' => $item['strength'],
                    'manufacturer_name' => $manufacturer->name,
                    'conversion' => $unit->factor,
                    'rack_location' => 'R'.(($index % 12) + 1).'-S'.(($index % 6) + 1),
                    'previous_price' => round($item['mrp'] * 0.94, 2),
                    'mrp' => $item['mrp'],
                    'purchase_price' => $item['purchase_price'],
                    'selling_price' => $item['mrp'],
                    'cc_rate' => $manufacturer->default_cc_rate ?? 0,
                    'discount_percent' => [0, 2, 3, 5][$index % 4],
                    'reorder_level' => [12, 20, 30, 40][$index % 4],
                    'reorder_quantity' => [50, 80, 120, 160][$index % 4],
                    'is_batch_tracked' => true,
                    'is_active' => true,
                    'keywords' => Str::lower($item['name'].' '.$item['generic'].' '.$item['category']),
                    'description' => $item['category'].' stock item for demo operations.',
                    'notes' => null,
                    'created_by' => $context['owner']->id,
                    'updated_by' => $context['owner']->id,
                ],
            );
            $product->restore();
            $products[] = $product;
        }

        $context['products'] = $products;
    }

    private function seedRepresentatives(array &$context): void
    {
        $branches = $context['branches'];
        $mrs = collect(range(0, 9))->map(function (int $index) use ($context, $branches) {
            $name = $this->personName($index + 4);
            $mr = MedicalRepresentative::query()->withTrashed()->updateOrCreate(
                ['tenant_id' => $context['tenant']->id, 'employee_code' => 'MR-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)],
                [
                    'company_id' => $context['company']->id,
                    'branch_id' => $branches[$index % $branches->count()]->id,
                    'name' => $name,
                    'phone' => '98'.(62000000 + $index * 3901),
                    'email' => Str::slug($name, '.').'@mr.test',
                    'territory' => $this->places[$index % count($this->places)],
                    'monthly_target' => [75000, 90000, 120000, 150000][$index % 4],
                    'is_active' => true,
                    'created_by' => $context['owner']->id,
                    'updated_by' => $context['owner']->id,
                ],
            );
            $mr->restore();

            $user = User::query()->updateOrCreate(
                ['email' => Str::slug($name, '.').'@mr.test'],
                [
                    'tenant_id' => $context['tenant']->id,
                    'company_id' => $context['company']->id,
                    'store_id' => $context['store']->id,
                    'branch_id' => $mr->branch_id,
                    'medical_representative_id' => $mr->id,
                    'name' => $name,
                    'phone' => $mr->phone,
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'is_owner' => false,
                    'is_active' => true,
                ],
            );
            $user->assignRole('MR');

            return $mr;
        })->values();

        $context['mrs'] = $mrs->all();
    }

    private function seedPurchaseOrdersAndEntries(array &$context): void
    {
        $orderService = app(PurchaseOrderService::class);
        $purchaseService = app(PurchaseEntryService::class);
        $products = collect($context['products']);
        $suppliers = collect($context['suppliers']);
        $owner = $context['owner'];

        for ($i = 0; $i < 10; $i++) {
            $date = $this->today->copy()->subMonths(5)->addDays($i * 12);
            $items = $products->slice($i * 3, 5)->values()->map(fn (Product $product, int $line) => [
                'product_id' => $product->id,
                'quantity' => 60 + (($i + $line) % 5) * 20,
                'unit_price' => (float) $product->purchase_price,
                'discount_percent' => [0, 1, 2][$line % 3],
                'notes' => null,
            ])->all();

            $order = $orderService->create([
                'supplier_id' => $suppliers[$i % $suppliers->count()]->id,
                'order_date' => $date->toDateString(),
                'expected_date' => $date->copy()->addDays(7)->toDateString(),
                'notes' => 'Demo purchase order for planned replenishment.',
                'items' => $items,
            ], $owner);

            if ($i < 7) {
                $receiveItems = $order->items->map(fn ($item, int $line) => [
                    'purchase_order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'batch_no' => 'PO'.$date->format('ymd').'-'.$item->product_id,
                    'barcode' => 'BPO'.str_pad((string) $item->product_id, 10, '0', STR_PAD_LEFT),
                    'manufactured_at' => $date->copy()->subMonths(2)->toDateString(),
                    'expires_at' => $this->expiryFor($date, $i + $line),
                    'quantity' => $item->quantity,
                    'free_quantity' => $line % 3 === 0 ? 5 : 0,
                    'purchase_price' => $item->unit_price,
                    'mrp' => (float) $item->product->mrp,
                    'cc_rate' => (float) ($item->product->cc_rate ?? 0),
                    'discount_percent' => $item->discount_percent,
                ])->all();

                $orderService->receive($order, [
                    'supplier_invoice_no' => 'SUP-PO-'.$date->format('ymd').'-'.$i,
                    'purchase_date' => $date->copy()->addDays(4)->toDateString(),
                    'paid_amount' => $i % 3 === 0 ? 0 : round($this->purchaseLineTotal($receiveItems) * 0.5, 2),
                    'notes' => 'Received through demo PO receive workflow.',
                    'items' => $receiveItems,
                ], $owner, $purchaseService);
            }
        }

        for ($i = 0; $i < 36; $i++) {
            $date = $this->today->copy()->subMonths(5)->addDays($i * 5);
            $items = collect(range(0, 7))->map(function (int $line) use ($products, $date, $i) {
                $product = $products[($i * 7 + $line * 3) % $products->count()];
                $quantity = 90 + (($i + $line) % 7) * 15;

                return [
                    'product_id' => $product->id,
                    'batch_no' => 'PB'.$date->format('ymd').'-'.str_pad((string) $product->id, 4, '0', STR_PAD_LEFT).'-'.$line,
                    'barcode' => 'BT'.str_pad((string) (($i + 1) * 1000 + $line), 10, '0', STR_PAD_LEFT),
                    'manufactured_at' => $date->copy()->subMonths(2 + ($line % 4))->toDateString(),
                    'expires_at' => $this->expiryFor($date, $i + $line),
                    'quantity' => $quantity,
                    'free_quantity' => $line % 4 === 0 ? 10 : 0,
                    'purchase_price' => (float) $product->purchase_price,
                    'mrp' => (float) $product->mrp,
                    'cc_rate' => (float) ($product->cc_rate ?? 0),
                    'discount_percent' => [0, 1.5, 2, 3][$line % 4],
                ];
            })->all();

            $purchaseService->create([
                'supplier_id' => $suppliers[$i % $suppliers->count()]->id,
                'supplier_invoice_no' => 'SUP-'.$date->format('ymd').'-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'purchase_date' => $date->toDateString(),
                'paid_amount' => $i % 4 === 0 ? 0 : round($this->purchaseLineTotal($items) * 0.6, 2),
                'notes' => 'Seeded purchase with batch and stock movement posting.',
                'items' => $items,
            ], $owner);
        }
    }

    private function seedSalesInvoices(array &$context): void
    {
        $salesService = app(SalesInvoiceService::class);
        $customers = collect($context['customers']);
        $mrs = collect($context['mrs']);
        $owner = $context['owner'];

        for ($i = 0; $i < 220; $i++) {
            $date = $this->today->copy()->subMonths(5)->addDays($i % 170);
            $walkIn = $i % 6 === 0;
            $customer = $walkIn ? $customers->firstWhere('name', 'Walk-in Customer') : $customers[(($i * 5) % ($customers->count() - 1)) + 1];
            $lineCount = 2 + ($i % 4);
            $items = [];
            $usedProducts = [];

            for ($line = 0; $line < $lineCount; $line++) {
                $quantity = 1 + (($i + $line) % 5);
                $batch = $this->availableBatch($context['company']->id, $quantity, $usedProducts);

                if (! $batch) {
                    break;
                }

                $usedProducts[] = $batch->product_id;
                $discount = $walkIn ? 0 : [0, 2, 3, 5][($i + $line) % 4];

                $items[] = [
                    'product_id' => $batch->product_id,
                    'batch_id' => $batch->id,
                    'quantity' => $quantity,
                    'free_quantity' => $line === 0 && ! $walkIn && $i % 11 === 0 ? 1 : 0,
                    'mrp' => (float) $batch->mrp,
                    'unit_price' => (float) $batch->mrp,
                    'cc_rate' => (float) ($batch->product?->cc_rate ?? 0),
                    'discount_percent' => $discount,
                ];
            }

            if (count($items) < 2) {
                continue;
            }

            $invoice = $salesService->create([
                'customer_id' => $customer?->id,
                'medical_representative_id' => $walkIn ? null : $mrs[$i % $mrs->count()]->id,
                'invoice_date' => $date->toDateString(),
                'sale_type' => $walkIn ? 'pos' : ($i % 5 === 0 ? 'wholesale' : 'retail'),
                'paid_amount' => $walkIn ? $this->lineTotal($items) : ($i % 4 === 0 ? 0 : round($this->lineTotal($items) * 0.55, 2)),
                'notes' => $walkIn ? 'Counter sale demo invoice.' : 'Field/order sale demo invoice.',
                'items' => $items,
            ], $owner);

            if ($i % 9 === 0 && (float) $invoice->paid_amount < (float) $invoice->grand_total) {
                $salesService->updatePayment($invoice, [
                    'paid_amount' => round((float) $invoice->grand_total, 2),
                    'cash_account' => $i % 2 === 0 ? 'cash' : 'bank',
                ], $owner);
            }
        }

        $context['sales'] = SalesInvoice::query()
            ->where('tenant_id', $context['tenant']->id)
            ->latest('invoice_date')
            ->limit(80)
            ->get()
            ->all();
    }

    private function seedReturnsAndAdjustments(array $context): void
    {
        $adjustmentService = app(StockAdjustmentService::class);
        $owner = $context['owner'];

        $batches = Batch::query()
            ->where('company_id', $context['company']->id)
            ->where('quantity_available', '>', 30)
            ->orderBy('expires_at')
            ->limit(12)
            ->get();

        foreach ($batches->take(8) as $index => $batch) {
            if (StockAdjustment::query()->where('batch_id', $batch->id)->where('reason', 'Demo physical count adjustment')->exists()) {
                continue;
            }

            $adjustmentService->save([
                'adjustment_date' => $this->today->copy()->subDays(20 - $index)->toDateString(),
                'product_id' => $batch->product_id,
                'batch_id' => $batch->id,
                'adjustment_type' => $index % 3 === 0 ? 'damaged' : 'subtract',
                'quantity' => 1 + ($index % 3),
                'reason' => 'Demo physical count adjustment',
            ], $owner);
        }
    }

    private function seedPaymentsExpensesAndVouchers(array $context): void
    {
        $paymentModes = DropdownOption::query()->forAlias('payment_mode')->active()->get()->values();
        $expenseCategories = DropdownOption::query()->forAlias('expense_category')->active()->get()->values();
        $owner = $context['owner'];
        $customers = collect($context['customers'])->where('name', '<>', 'Walk-in Customer')->values();
        $suppliers = collect($context['suppliers'])->values();

        for ($i = 0; $i < 48; $i++) {
            $direction = $i % 2 === 0 ? 'in' : 'out';
            $partyType = $direction === 'in' ? 'customer' : 'supplier';
            $party = $direction === 'in' ? $customers[$i % $customers->count()] : $suppliers[$i % $suppliers->count()];
            $mode = $paymentModes[$i % $paymentModes->count()];
            $amount = [1250, 2500, 4800, 7200, 10500, 15000][$i % 6];
            $date = $this->today->copy()->subDays(150 - $i * 2)->toDateString();

            $payment = Payment::query()->firstOrCreate(
                ['payment_no' => 'PAY-DEMO-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT)],
                [
                    'tenant_id' => $context['tenant']->id,
                    'company_id' => $context['company']->id,
                    'store_id' => $context['store']->id,
                    'payment_date' => $date,
                    'direction' => $direction,
                    'party_type' => $partyType,
                    'party_id' => $party->id,
                    'payment_mode_id' => $mode->id,
                    'payment_mode' => $mode->data ?: Str::slug($mode->name, '_'),
                    'amount' => $amount,
                    'reference_no' => 'REF-'.$this->today->format('ym').'-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                    'notes' => $direction === 'in' ? 'Demo customer receipt.' : 'Demo supplier payment.',
                    'created_by' => $owner->id,
                    'updated_by' => $owner->id,
                ],
            );

            $cashAccount = ($mode->data ?: 'cash') === 'cash' ? 'cash' : 'bank';
            $this->postPaymentAccounting($payment, $cashAccount);
        }

        foreach (range(0, 23) as $i) {
            $category = $expenseCategories[$i % $expenseCategories->count()];

            Expense::query()->firstOrCreate(
                [
                    'tenant_id' => $context['tenant']->id,
                    'company_id' => $context['company']->id,
                    'expense_date' => $this->today->copy()->subDays($i * 6)->toDateString(),
                    'category' => $category->name,
                    'amount' => [900, 1450, 2800, 5200, 8400][$i % 5],
                ],
                [
                    'expense_category_id' => $category->id,
                    'vendor_name' => ['Nepal Electricity Authority', 'House Rent', 'Pathao Delivery', 'Stationery Supplier'][$i % 4],
                    'payment_mode_id' => $paymentModes[$i % $paymentModes->count()]->id,
                    'payment_mode' => $paymentModes[$i % $paymentModes->count()]->data ?: 'cash',
                    'notes' => 'Demo operating expense.',
                    'created_by' => $owner->id,
                ],
            );
        }

        $voucherService = app(VoucherService::class);
        foreach (range(0, 10) as $i) {
            $amount = [3500, 7800, 12500, 22000][$i % 4];

            if (AccountTransaction::query()->where('source_type', 'voucher')->where('notes', 'like', '%Demo journal voucher '.$i.'%')->exists()) {
                continue;
            }

            $voucherService->create([
                'voucher_date' => $this->today->copy()->subDays(80 - $i * 5)->toDateString(),
                'voucher_type' => $i % 2 === 0 ? 'journal' : 'contra',
                'notes' => 'Demo journal voucher '.$i,
                'entries' => [
                    ['account_type' => $i % 2 === 0 ? 'expense' : 'bank', 'entry_type' => 'debit', 'amount' => $amount, 'notes' => 'Demo journal voucher '.$i],
                    ['account_type' => $i % 2 === 0 ? 'cash' : 'cash', 'entry_type' => 'credit', 'amount' => $amount, 'notes' => 'Demo journal voucher '.$i],
                ],
            ], $owner);
        }
    }

    private function seedVisits(array $context): void
    {
        $mrs = collect($context['mrs']);
        $customers = collect($context['customers'])->where('name', '<>', 'Walk-in Customer')->values();
        $owner = $context['owner'];

        foreach (range(0, 280) as $i) {
            $mr = $mrs[$i % $mrs->count()];
            $customer = $customers[($i * 7) % $customers->count()];
            $date = $this->today->copy()->subDays(170 - ($i % 170));

            RepresentativeVisit::query()->firstOrCreate(
                [
                    'medical_representative_id' => $mr->id,
                    'customer_id' => $customer->id,
                    'visit_date' => $date->toDateString(),
                ],
                [
                    'status' => ['completed', 'completed', 'follow_up', 'missed'][$i % 4],
                    'order_value' => $i % 4 === 3 ? 0 : [1800, 3500, 7200, 12500, 21000][$i % 5],
                    'notes' => 'Demo field visit at '.$customer->address.'.',
                    'latitude' => 27.65 + (($i % 20) / 1000),
                    'longitude' => 85.29 + (($i % 20) / 1000),
                    'location_name' => $customer->address,
                    'created_by' => $owner->id,
                    'updated_by' => $owner->id,
                ],
            );
        }
    }

    private function availableBatch(int $companyId, float $quantity, array $excludeProductIds = []): ?Batch
    {
        $query = Batch::query()
            ->with('product')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('quantity_available', '>=', $quantity + 3)
            ->whereNull('deleted_at')
            ->orderBy('expires_at')
            ->orderBy('id');

        if ($excludeProductIds !== []) {
            $query->whereNotIn('product_id', $excludeProductIds);
        }

        return (clone $query)->inRandomOrder()->first() ?: $query->first();
    }

    private function lineTotal(array $items): float
    {
        return round(collect($items)->sum(function (array $item) {
            $gross = (float) $item['quantity'] * (float) $item['unit_price'];

            return $gross - ($gross * (float) ($item['discount_percent'] ?? 0) / 100);
        }), 2);
    }

    private function purchaseLineTotal(array $items): float
    {
        return round(collect($items)->sum(function (array $item) {
            $gross = (float) $item['quantity'] * (float) $item['purchase_price'];

            return $gross - ($gross * (float) ($item['discount_percent'] ?? 0) / 100);
        }), 2);
    }

    private function postPaymentAccounting(Payment $payment, string $cashAccount): void
    {
        AccountTransaction::query()
            ->where('source_type', 'Payment')
            ->where('source_id', $payment->id)
            ->delete();

        $debit = $payment->direction === 'in'
            ? [$cashAccount, $payment->amount, 'Payment received.']
            : ['payable', $payment->amount, 'Supplier payment adjusted.'];
        $credit = $payment->direction === 'in'
            ? ['receivable', $payment->amount, 'Customer receipt adjusted.']
            : [$cashAccount, $payment->amount, 'Money paid out.'];

        foreach ([['debit', ...$debit], ['credit', ...$credit]] as $entry) {
            [$side, $accountType, $amount, $notes] = $entry;

            AccountTransaction::query()->create([
                'tenant_id' => $payment->tenant_id,
                'company_id' => $payment->company_id,
                'transaction_date' => $payment->payment_date,
                'account_type' => $accountType,
                'party_type' => $payment->party_type,
                'party_id' => $payment->party_id,
                'source_type' => 'Payment',
                'source_id' => $payment->id,
                'debit' => $side === 'debit' ? $amount : 0,
                'credit' => $side === 'credit' ? $amount : 0,
                'notes' => $notes,
                'created_by' => $payment->created_by,
            ]);
        }
    }

    private function productCatalog(): array
    {
        $base = [
            ['Paracetamol 500mg', 'Paracetamol', 'Paracetamol 500mg', 'Tablet', '500mg', 'Analgesic', 35, 19],
            ['Ibuprofen 400mg', 'Ibuprofen', 'Ibuprofen 400mg', 'Tablet', '400mg', 'Analgesic', 48, 26],
            ['Diclofenac 50mg', 'Diclofenac Sodium', 'Diclofenac 50mg', 'Tablet', '50mg', 'Analgesic', 62, 34],
            ['Amoxicillin 500mg', 'Amoxicillin', 'Amoxicillin 500mg', 'Capsule', '500mg', 'Antibiotic', 118, 64],
            ['Azithromycin 500mg', 'Azithromycin', 'Azithromycin 500mg', 'Tablet', '500mg', 'Antibiotic', 185, 96],
            ['Cefixime 200mg', 'Cefixime', 'Cefixime 200mg', 'Tablet', '200mg', 'Antibiotic', 165, 91],
            ['Metformin 500mg', 'Metformin', 'Metformin 500mg', 'Tablet', '500mg', 'Antidiabetic', 55, 30],
            ['Glimepiride 2mg', 'Glimepiride', 'Glimepiride 2mg', 'Tablet', '2mg', 'Antidiabetic', 92, 51],
            ['Amlodipine 5mg', 'Amlodipine', 'Amlodipine 5mg', 'Tablet', '5mg', 'Cardiac', 75, 39],
            ['Losartan 50mg', 'Losartan', 'Losartan 50mg', 'Tablet', '50mg', 'Cardiac', 90, 48],
            ['Atorvastatin 10mg', 'Atorvastatin', 'Atorvastatin 10mg', 'Tablet', '10mg', 'Cardiac', 125, 69],
            ['Pantoprazole 40mg', 'Pantoprazole', 'Pantoprazole 40mg', 'Tablet', '40mg', 'GI', 112, 58],
            ['Omeprazole 20mg', 'Omeprazole', 'Omeprazole 20mg', 'Capsule', '20mg', 'GI', 95, 49],
            ['ORS Sachet', 'ORS', 'WHO ORS', 'Sachet', '20.5g', 'GI', 18, 9],
            ['Cetirizine 10mg', 'Cetirizine', 'Cetirizine 10mg', 'Tablet', '10mg', 'Respiratory', 40, 21],
            ['Montelukast 10mg', 'Montelukast', 'Montelukast 10mg', 'Tablet', '10mg', 'Respiratory', 140, 78],
            ['Cough Syrup 100ml', 'Dextromethorphan', 'Dextromethorphan syrup', 'Syrup', '100ml', 'Respiratory', 125, 68],
            ['Multivitamin Syrup 200ml', 'Multivitamin', 'Multivitamin syrup', 'Syrup', '200ml', 'Supplement', 180, 98],
            ['Vitamin D3 60K', 'Cholecalciferol', 'Vitamin D3 60000 IU', 'Capsule', '60K IU', 'Supplement', 250, 136],
            ['Iron Folic Acid', 'Iron + Folic Acid', 'Iron folic acid', 'Tablet', '60mg+400mcg', 'Supplement', 68, 36],
            ['Betadine Ointment', 'Povidone Iodine', 'Povidone iodine ointment', 'Ointment', '15g', 'Antiseptic', 95, 52],
            ['Chlorhexidine Solution', 'Chlorhexidine', 'Chlorhexidine solution', 'Bottle', '100ml', 'Antiseptic', 135, 74],
            ['Mupirocin Ointment', 'Mupirocin', 'Mupirocin ointment', 'Ointment', '5g', 'Dermatology', 220, 124],
            ['Normal Saline 500ml', 'Sodium Chloride', '0.9% Sodium chloride', 'Injection', '500ml', 'IV Fluid', 48, 25],
            ['DNS 500ml', 'Dextrose + Sodium Chloride', 'DNS IV fluid', 'Injection', '500ml', 'IV Fluid', 54, 29],
            ['Ciprofloxacin Eye Drops', 'Ciprofloxacin', 'Ciprofloxacin eye drops', 'Drops', '5ml', 'Eye / ENT', 82, 45],
            ['Salbutamol Inhaler', 'Salbutamol', 'Salbutamol inhaler', 'Bottle', '100 dose', 'Respiratory', 310, 184],
            ['Adrenaline Injection', 'Adrenaline', 'Adrenaline injection', 'Injection', '1ml', 'Emergency', 85, 47],
            ['Hydrocortisone Injection', 'Hydrocortisone', 'Hydrocortisone injection', 'Injection', '100mg', 'Emergency', 140, 78],
            ['Ondansetron 4mg', 'Ondansetron', 'Ondansetron 4mg', 'Tablet', '4mg', 'GI', 78, 42],
            ['Domperidone 10mg', 'Domperidone', 'Domperidone 10mg', 'Tablet', '10mg', 'GI', 44, 23],
            ['Levofloxacin 500mg', 'Levofloxacin', 'Levofloxacin 500mg', 'Tablet', '500mg', 'Antibiotic', 175, 96],
            ['Clavulanate Duo 625', 'Amoxicillin + Clavulanate', 'Co-amoxiclav 625mg', 'Tablet', '625mg', 'Antibiotic', 245, 139],
            ['Insulin Regular Vial', 'Regular Insulin', 'Regular insulin vial', 'Vial', '10ml', 'Antidiabetic', 540, 330],
            ['Telmisartan 40mg', 'Telmisartan', 'Telmisartan 40mg', 'Tablet', '40mg', 'Cardiac', 118, 64],
            ['Rosuvastatin 10mg', 'Rosuvastatin', 'Rosuvastatin 10mg', 'Tablet', '10mg', 'Cardiac', 160, 88],
            ['Calcium + D3', 'Calcium + Vitamin D3', 'Calcium D3 tablet', 'Tablet', '500mg', 'Supplement', 125, 69],
            ['Zinc Tablet', 'Zinc Sulphate', 'Zinc sulphate tablet', 'Tablet', '20mg', 'Supplement', 32, 17],
            ['Azithromycin Suspension', 'Azithromycin', 'Azithromycin suspension', 'Syrup', '15ml', 'Antibiotic', 155, 83],
            ['Loratadine 10mg', 'Loratadine', 'Loratadine 10mg', 'Tablet', '10mg', 'Respiratory', 65, 34],
        ];

        return collect($base)->map(fn (array $row, int $index) => [
            'sku' => 'MED-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
            'name' => $row[0],
            'generic' => $row[1],
            'composition' => $row[2],
            'formulation' => $row[3],
            'strength' => $row[4],
            'category' => $row[5],
            'mrp' => $row[6],
            'purchase_price' => $row[7],
        ])->all();
    }

    private function unitForFormulation(string $formulation, array $units): Unit
    {
        return match ($formulation) {
            'Syrup', 'Bottle' => $units['Bottle'],
            'Injection' => $units['Vial'],
            'Ointment' => $units['Tube'],
            'Sachet' => $units['Sachet'],
            default => $units['Strip'] ?? $units['Piece'],
        };
    }

    private function expiryFor(Carbon $baseDate, int $offset): string
    {
        if ($offset % 17 === 0) {
            return $this->today->copy()->addDays(45 + ($offset % 20))->toDateString();
        }

        return $baseDate->copy()->addMonths(14 + ($offset % 18))->endOfMonth()->toDateString();
    }

    private function personName(int $index): string
    {
        return $this->firstNames[$index % count($this->firstNames)].' '.$this->lastNames[$index % count($this->lastNames)];
    }
}
