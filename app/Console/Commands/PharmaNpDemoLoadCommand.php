<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Modules\Setup\Services\AccessControlService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class PharmaNpDemoLoadCommand extends Command
{
    protected $signature = 'pharmanp:demo-load
        {--profile=showcase : tiny, showcase, demo50, stress, scale10m, or scale20m}
        {--tenants= : Override tenant count}
        {--branches= : Branches per tenant}
        {--users= : Total staff users}
        {--products= : Total products}
        {--customers= : Total customers}
        {--suppliers= : Total suppliers}
        {--batches= : Total batches}
        {--purchases= : Total purchase bills}
        {--sales= : Total sales invoices}
        {--chunk=500 : Insert chunk size}
        {--dry-run : Print the plan without writing}
        {--yes : Skip confirmation}';

    protected $description = 'Create realistic multi-tenant demo/load data in chunks for performance demos.';

    private array $firstNames = ['Pratik', 'Ranjan', 'Alika', 'Sampanna', 'Dharma', 'Gokul', 'Abhisek', 'Mandip', 'Srijal', 'Utakrsha', 'Khush', 'Ryan', 'Sonu', 'Smriti', 'Pooja'];
    private array $lastNames = ['Bhujel', 'Dangol', 'Shrestha', 'Maharjan', 'Rimal', 'Gumaju', 'Adhikari', 'Subedi', 'Aryal', 'Dungana'];
    private array $places = ['Kathmandu', 'Pokhara', 'Patan', 'Bhaktapur', 'Baneshwor', 'Maharajgunj', 'New Road', 'Thamel', 'Kalanki', 'Koteshwor', 'Chabahil', 'Boudha', 'Lakeside Pokhara', 'Prithvi Chowk', 'Srijana Chowk'];
    private array $formulations = ['Tablet', 'Capsule', 'Syrup', 'Injection', 'Ointment', 'Drops', 'Sachet'];
    private array $categories = ['Analgesic', 'Antibiotic', 'Antidiabetic', 'Cardiac', 'GI', 'Respiratory', 'Supplement', 'Antiseptic', 'Dermatology', 'Emergency'];

    public function handle(): int
    {
        DB::disableQueryLog();

        $counts = $this->counts();
        $chunk = min(max((int) $this->option('chunk'), 50), 5000);
        $runCode = now()->format('ymdHis');

        $this->line('Connection: '.config('database.default').' / '.DB::connection()->getDatabaseName());
        $this->table(['Metric', 'Rows'], collect($counts)->map(fn ($value, $key) => [$key, number_format($value)])->all());
        $this->line('Estimated physical rows: '.number_format($this->estimatedRows($counts)));

        if ($this->option('dry-run')) {
            $this->components->info('Dry run only. No rows were written.');

            return self::SUCCESS;
        }

        if (! $this->option('yes') && ! $this->confirm('Write this demo/load dataset to the current database?')) {
            return self::SUCCESS;
        }

        app(AccessControlService::class)->syncPermissions();
        $this->ensureOwnerRole();

        $started = microtime(true);
        $tenantCount = max((int) $counts['tenants'], 1);

        for ($tenantNo = 1; $tenantNo <= $tenantCount; $tenantNo++) {
            $context = $this->seedTenantFoundation($tenantNo, $counts, $runCode);
            $this->seedSuppliers($context, $this->share($counts['suppliers'], $tenantCount, $tenantNo), $chunk, $runCode);
            $this->seedCustomers($context, $this->share($counts['customers'], $tenantCount, $tenantNo), $chunk, $runCode);
            $this->seedUsers($context, $this->share($counts['users'], $tenantCount, $tenantNo), $chunk, $runCode);
            $this->seedEmployees($context, $this->share($counts['users'], $tenantCount, $tenantNo), $chunk, $runCode);
            $this->seedRepresentatives($context, max(3, min(30, (int) ceil($this->share($counts['users'], $tenantCount, $tenantNo) / 8))), $chunk, $runCode);
            $this->seedProducts($context, $this->share($counts['products'], $tenantCount, $tenantNo), $chunk, $runCode);
            $this->seedBatches($context, $this->share($counts['batches'], $tenantCount, $tenantNo), $chunk, $runCode);
            $this->seedPurchases($context, $this->share($counts['purchases'], $tenantCount, $tenantNo), $chunk, $runCode);
            $this->seedSales($context, $this->share($counts['sales'], $tenantCount, $tenantNo), $chunk, $runCode);
            $this->seedRepresentativeVisits($context, min(max((int) ceil($this->share($counts['sales'], $tenantCount, $tenantNo) / 3), 20), 5000), $chunk, $runCode);
            $this->seedTargets($context, $runCode);

            $this->line('Tenant '.$tenantNo.'/'.$tenantCount.' loaded.');
        }

        Setting::putValue('app.installed', [
            'installed' => true,
            'seeded_demo' => true,
            'loaded_at' => now()->toISOString(),
            'profile' => $this->option('profile'),
        ], true);
        Setting::putValue('demo.load.last_run', [
            'run_code' => $runCode,
            'counts' => $counts,
            'database' => DB::connection()->getDatabaseName(),
        ], true);

        $seconds = round(microtime(true) - $started, 2);
        $this->components->info('Demo/load data completed in '.$seconds.'s. Login any tenant owner with password: done');

        return self::SUCCESS;
    }

    private function counts(): array
    {
        $profile = (string) $this->option('profile');
        $profiles = [
            'tiny' => ['tenants' => 2, 'branches' => 2, 'users' => 8, 'products' => 60, 'customers' => 120, 'suppliers' => 24, 'batches' => 120, 'purchases' => 40, 'sales' => 180],
            'showcase' => ['tenants' => 12, 'branches' => 3, 'users' => 180, 'products' => 3000, 'customers' => 6000, 'suppliers' => 360, 'batches' => 9000, 'purchases' => 3000, 'sales' => 18000],
            'demo50' => ['tenants' => 50, 'branches' => 4, 'users' => 1500, 'products' => 50000, 'customers' => 100000, 'suppliers' => 5000, 'batches' => 200000, 'purchases' => 100000, 'sales' => 250000],
            'stress' => ['tenants' => 100, 'branches' => 5, 'users' => 2500, 'products' => 100000, 'customers' => 250000, 'suppliers' => 10000, 'batches' => 400000, 'purchases' => 500000, 'sales' => 1000000],
            'scale10m' => ['tenants' => 200, 'branches' => 5, 'users' => 5000, 'products' => 250000, 'customers' => 500000, 'suppliers' => 20000, 'batches' => 1000000, 'purchases' => 750000, 'sales' => 1500000],
            'scale20m' => ['tenants' => 300, 'branches' => 8, 'users' => 9000, 'products' => 500000, 'customers' => 1000000, 'suppliers' => 40000, 'batches' => 2000000, 'purchases' => 1500000, 'sales' => 3000000],
        ];

        $counts = $profiles[$profile] ?? $profiles['showcase'];

        if (str_starts_with($profile, 'scale') && DB::connection()->getDriverName() === 'sqlite') {
            throw new \RuntimeException('Scale profiles require MySQL/MariaDB. Use tiny/showcase for SQLite.');
        }

        foreach (array_keys($counts) as $key) {
            if ($this->option($key) !== null) {
                $counts[$key] = max((int) $this->option($key), $key === 'tenants' ? 1 : 0);
            }
        }

        return $counts;
    }

    private function estimatedRows(array $counts): int
    {
        return $counts['tenants']
            + $counts['tenants'] * 5
            + $counts['branches'] * $counts['tenants']
            + $counts['users']
            + $counts['suppliers']
            + $counts['customers']
            + $counts['products']
            + $counts['batches']
            + $counts['purchases'] * 4
            + $counts['sales'] * 6;
    }

    private function seedTenantFoundation(int $tenantNo, array $counts, string $runCode): array
    {
        $now = now();
        $place = $this->places[($tenantNo - 1) % count($this->places)];
        $name = $place.' Care Pharmacy '.$tenantNo;
        $tenantSlug = 'demo-'.$runCode.'-'.str_pad((string) $tenantNo, 3, '0', STR_PAD_LEFT);

        DB::table('tenants')->insert([
            'name' => $name,
            'slug' => $tenantSlug,
            'status' => 'active',
            'plan_code' => 'load-demo',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $tenantId = (int) DB::table('tenants')->where('slug', $tenantSlug)->value('id');

        DB::table('companies')->insert([
            'tenant_id' => $tenantId,
            'name' => $name,
            'legal_name' => $name.' Pvt. Ltd.',
            'pan_number' => (string) (700000000 + $tenantNo),
            'phone' => '01-'.(5000000 + $tenantNo),
            'email' => 'owner'.$tenantNo.'@demo.pharmanp.test',
            'address' => $place,
            'country' => 'NP',
            'company_type' => 'pharmacy',
            'default_cc_rate' => 0,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $companyId = (int) DB::table('companies')->where('tenant_id', $tenantId)->where('name', $name)->value('id');

        DB::table('stores')->insert([
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'name' => 'Main Store',
            'code' => 'MAIN-'.$tenantNo,
            'phone' => '01-'.(5100000 + $tenantNo),
            'address' => $place,
            'is_default' => true,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $storeId = (int) DB::table('stores')->where('tenant_id', $tenantId)->where('code', 'MAIN-'.$tenantNo)->value('id');

        $branchIds = $this->seedBranches($tenantId, $companyId, $storeId, $counts['branches'], $tenantNo, $now);
        $ownerId = $this->seedOwner($tenantId, $companyId, $storeId, $branchIds[0] ?? null, $tenantNo, $name, $now);
        $areaIds = $this->seedAreas($tenantId, $companyId, $branchIds, $ownerId, $tenantNo, $now);
        $divisionIds = $this->seedDivisions($tenantId, $companyId, $ownerId, $tenantNo, $now);
        $unitIds = $this->seedUnits($tenantId, $companyId, $ownerId, $now);
        $categoryIds = $this->seedCategories($tenantId, $companyId, $ownerId, $now);
        $supplierTypeIds = $this->seedSupplierTypes($now);
        $partyTypeIds = $this->seedPartyTypes($now);
        $this->seedDropdowns($now);

        return compact('tenantId', 'companyId', 'storeId', 'branchIds', 'areaIds', 'divisionIds', 'ownerId', 'unitIds', 'categoryIds', 'supplierTypeIds', 'partyTypeIds', 'tenantNo');
    }

    private function seedBranches(int $tenantId, int $companyId, int $storeId, int $count, int $tenantNo, mixed $now): array
    {
        $rows = [];
        $count = max($count, 1);

        for ($i = 1; $i <= $count; $i++) {
            $place = $this->places[($tenantNo + $i) % count($this->places)];
            $rows[] = [
                'tenant_id' => $tenantId,
                'company_id' => $companyId,
                'store_id' => $storeId,
                'name' => $i === 1 ? 'Head Office' : $place.' Branch',
                'code' => 'T'.$tenantNo.'-BR'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'type' => $i === 1 ? 'hq' : 'branch',
                'address' => $place,
                'phone' => '98'.str_pad((string) (41000000 + $tenantNo * 1000 + $i), 8, '0', STR_PAD_LEFT),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('branches')->insert($rows);

        return DB::table('branches')->where('tenant_id', $tenantId)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function seedAreas(int $tenantId, int $companyId, array $branchIds, int $ownerId, int $tenantNo, mixed $now): array
    {
        $rows = [];

        foreach ($branchIds as $index => $branchId) {
            foreach (range(1, 2) as $areaNo) {
                $place = $this->places[($tenantNo + $index + $areaNo) % count($this->places)];
                $rows[] = [
                    'tenant_id' => $tenantId,
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'name' => $place.' Area',
                    'code' => 'A'.$tenantNo.'-'.str_pad((string) ($index * 2 + $areaNo), 2, '0', STR_PAD_LEFT),
                    'district' => $place,
                    'province' => $index % 2 === 0 ? 'Bagmati' : 'Gandaki',
                    'is_active' => true,
                    'created_by' => $ownerId,
                    'updated_by' => $ownerId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $this->insertChunked('areas', $rows, 500);

        return DB::table('areas')->where('tenant_id', $tenantId)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function seedDivisions(int $tenantId, int $companyId, int $ownerId, int $tenantNo, mixed $now): array
    {
        $rows = [];

        foreach (['General', 'Cardio-Diabetic', 'Antibiotic', 'Surgical', 'Wellness'] as $index => $name) {
            $rows[] = [
                'tenant_id' => $tenantId,
                'company_id' => $companyId,
                'name' => $name,
                'code' => 'D'.$tenantNo.'-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                'notes' => null,
                'is_active' => true,
                'created_by' => $ownerId,
                'updated_by' => $ownerId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertChunked('divisions', $rows, 500);

        return DB::table('divisions')->where('tenant_id', $tenantId)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function seedOwner(int $tenantId, int $companyId, int $storeId, ?int $branchId, int $tenantNo, string $companyName, mixed $now): int
    {
        $email = $tenantNo === 1 ? 'pratik@admin.com' : 'owner'.$tenantNo.'@demo.pharmanp.test';

        DB::table('users')->updateOrInsert(
            ['email' => $email],
            [
                'tenant_id' => $tenantId,
                'company_id' => $companyId,
                'store_id' => $storeId,
                'branch_id' => $branchId,
                'name' => $tenantNo === 1 ? 'Pratik Admin' : $companyName.' Owner',
                'phone' => '98'.str_pad((string) (50000000 + $tenantNo), 8, '0', STR_PAD_LEFT),
                'password' => Hash::make('done'),
                'email_verified_at' => $now,
                'is_owner' => true,
                'is_active' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        $ownerId = (int) DB::table('users')->where('email', $email)->value('id');
        $role = Role::query()->firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        $role->syncPermissions(app(AccessControlService::class)->permissionNames());
        $user = \App\Models\User::query()->find($ownerId);
        $user?->assignRole($role);

        return $ownerId;
    }

    private function seedUnits(int $tenantId, int $companyId, int $ownerId, mixed $now): array
    {
        $rows = [
            ['Piece', 'PCS', 'both', 1],
            ['Strip', 'STRIP', 'sales', 10],
            ['Box', 'BOX', 'purchase', 100],
            ['Bottle', 'BTL', 'both', 1],
            ['Vial', 'VIAL', 'both', 1],
        ];

        foreach ($rows as $row) {
            DB::table('units')->updateOrInsert(
                ['tenant_id' => $tenantId, 'company_id' => $companyId, 'code' => $row[1]],
                [
                    'name' => $row[0],
                    'type' => $row[2],
                    'factor' => $row[3],
                    'is_active' => true,
                    'created_by' => $ownerId,
                    'updated_by' => $ownerId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        return DB::table('units')->where('tenant_id', $tenantId)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function seedCategories(int $tenantId, int $companyId, int $ownerId, mixed $now): array
    {
        foreach ($this->categories as $name) {
            DB::table('product_categories')->updateOrInsert(
                ['tenant_id' => $tenantId, 'company_id' => $companyId, 'name' => $name],
                [
                    'code' => Str::upper(Str::slug($name, '')),
                    'is_active' => true,
                    'created_by' => $ownerId,
                    'updated_by' => $ownerId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        return DB::table('product_categories')->where('tenant_id', $tenantId)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function seedSupplierTypes(mixed $now): array
    {
        $rows = [
            ['Distributor', 'distributor'],
            ['Manufacturer Direct', 'manufacturer'],
            ['Institutional Supplier', 'institution'],
            ['Local Vendor', 'local'],
        ];

        foreach ($rows as $row) {
            DB::table('supplier_types')->updateOrInsert(['code' => $row[1]], ['name' => $row[0], 'created_at' => $now, 'updated_at' => $now]);
        }

        return DB::table('supplier_types')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function seedPartyTypes(mixed $now): array
    {
        $rows = [
            ['Walk-in Customer', 'walk_in'],
            ['Retail Customer', 'retail'],
            ['Wholesale Pharmacy', 'wholesale'],
            ['Clinic / Hospital', 'institution'],
        ];

        foreach ($rows as $row) {
            DB::table('party_types')->updateOrInsert(['code' => $row[1]], ['name' => $row[0], 'created_at' => $now, 'updated_at' => $now]);
        }

        return DB::table('party_types')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function seedDropdowns(mixed $now): void
    {
        foreach (['Cash', 'Bank Transfer', 'Cheque', 'FonePay QR', 'eSewa Wallet'] as $name) {
            DB::table('dropdown_options')->updateOrInsert(
                ['alias' => 'payment_mode', 'name' => $name],
                ['data' => Str::slug($name, '_'), 'status' => true, 'created_at' => $now, 'updated_at' => $now],
            );
        }
    }

    private function seedSuppliers(array $context, int $count, int $chunk, string $runCode): void
    {
        $rows = [];
        $now = now();
        $typeIds = $context['supplierTypeIds'];

        for ($i = 1; $i <= $count; $i++) {
            $name = $this->places[$i % count($this->places)].' Pharma Supplier '.$runCode.'-'.$context['tenantNo'].'-'.$i;
            $rows[] = [
                'tenant_id' => $context['tenantId'],
                'company_id' => $context['companyId'],
                'supplier_type_id' => $typeIds[$i % count($typeIds)] ?? null,
                'name' => $name,
                'contact_person' => $this->personName($i),
                'phone' => '98'.str_pad((string) (61000000 + $context['tenantNo'] * 10000 + $i), 8, '0', STR_PAD_LEFT),
                'email' => Str::slug($name, '.').'@supplier.demo',
                'pan_number' => (string) (800000000 + $context['tenantNo'] * 10000 + $i),
                'address' => $this->places[$i % count($this->places)],
                'opening_balance' => 0,
                'current_balance' => 0,
                'is_active' => true,
                'created_by' => $context['ownerId'],
                'updated_by' => $context['ownerId'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertChunked('suppliers', $rows, $chunk);
    }

    private function seedCustomers(array $context, int $count, int $chunk, string $runCode): void
    {
        $rows = [];
        $now = now();
        $typeIds = $context['partyTypeIds'];

        for ($i = 1; $i <= $count; $i++) {
            $walkIn = $i === 1;
            $place = $this->places[$i % count($this->places)];
            $name = $walkIn ? 'Walk-in Customer' : $this->personName($i).' '.$place.' Pharmacy '.$runCode.'-'.$context['tenantNo'].'-'.$i;
            $rows[] = [
                'tenant_id' => $context['tenantId'],
                'company_id' => $context['companyId'],
                'party_type_id' => $typeIds[$i % count($typeIds)] ?? null,
                'name' => $name,
                'contact_person' => $walkIn ? 'Counter Sale' : $this->personName($i + 11),
                'phone' => $walkIn ? null : '98'.str_pad((string) (71000000 + $context['tenantNo'] * 10000 + $i), 8, '0', STR_PAD_LEFT),
                'email' => $walkIn ? null : Str::slug($name, '.').'@customer.demo',
                'pan_number' => $walkIn ? null : (string) (810000000 + $context['tenantNo'] * 10000 + $i),
                'address' => $place,
                'credit_limit' => $walkIn ? 0 : [15000, 25000, 50000, 100000][$i % 4],
                'opening_balance' => 0,
                'current_balance' => 0,
                'is_active' => true,
                'created_by' => $context['ownerId'],
                'updated_by' => $context['ownerId'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertChunked('customers', $rows, $chunk);
    }

    private function seedUsers(array $context, int $count, int $chunk, string $runCode): void
    {
        $rows = [];
        $now = now();
        $password = Hash::make('done');

        for ($i = 1; $i <= $count; $i++) {
            $name = $this->personName($i + $context['tenantNo']);
            $rows[] = [
                'tenant_id' => $context['tenantId'],
                'company_id' => $context['companyId'],
                'store_id' => $context['storeId'],
                'branch_id' => $context['branchIds'][$i % count($context['branchIds'])] ?? null,
                'name' => $name,
                'email' => 'staff'.$context['tenantNo'].'-'.$runCode.'-'.$i.'@demo.pharmanp.test',
                'phone' => '98'.str_pad((string) (81000000 + $context['tenantNo'] * 10000 + $i), 8, '0', STR_PAD_LEFT),
                'password' => $password,
                'email_verified_at' => $now,
                'is_owner' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertChunked('users', $rows, $chunk);
    }

    private function seedEmployees(array $context, int $count, int $chunk, string $runCode): void
    {
        $users = DB::table('users')
            ->where('tenant_id', $context['tenantId'])
            ->orderByDesc('id')
            ->limit(max($count, 1))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
        $rows = [];
        $now = now();
        $managerIds = [];

        for ($i = 1; $i <= $count; $i++) {
            $designation = match (true) {
                $i % 25 === 0 => 'Area Manager',
                $i % 8 === 0 => 'Manager',
                default => 'Medical Representative',
            };

            $reportsTo = null;
            if ($designation === 'Manager' && $managerIds !== []) {
                $reportsTo = $managerIds[array_key_last($managerIds)];
            } elseif ($designation === 'Medical Representative' && $managerIds !== []) {
                $reportsTo = $managerIds[$i % count($managerIds)];
            }

            $rows[] = [
                'tenant_id' => $context['tenantId'],
                'company_id' => $context['companyId'],
                'user_id' => $users[($i - 1) % max(count($users), 1)] ?? null,
                'branch_id' => $context['branchIds'][$i % count($context['branchIds'])] ?? null,
                'area_id' => $context['areaIds'][$i % count($context['areaIds'])] ?? null,
                'division_id' => $context['divisionIds'][$i % count($context['divisionIds'])] ?? null,
                'reports_to_employee_id' => $reportsTo,
                'employee_code' => 'EMP-'.$context['tenantNo'].'-'.$runCode.'-'.$i,
                'name' => $this->personName($i + 40),
                'designation' => $designation,
                'phone' => '98'.str_pad((string) (84000000 + $context['tenantNo'] * 10000 + $i), 8, '0', STR_PAD_LEFT),
                'email' => 'employee'.$context['tenantNo'].'-'.$runCode.'-'.$i.'@demo.pharmanp.test',
                'joined_on' => CarbonImmutable::today()->subDays($i % 900)->toDateString(),
                'is_active' => true,
                'created_by' => $context['ownerId'],
                'updated_by' => $context['ownerId'],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) >= $chunk) {
                $this->insertChunked('employees', $rows, $chunk);
                $managerIds = DB::table('employees')
                    ->where('tenant_id', $context['tenantId'])
                    ->whereIn('designation', ['Manager', 'Area Manager'])
                    ->orderByDesc('id')
                    ->limit(100)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
                $rows = [];
            }
        }

        $this->insertChunked('employees', $rows, $chunk);
    }

    private function seedRepresentatives(array $context, int $count, int $chunk, string $runCode): void
    {
        $rows = [];
        $now = now();
        $employees = DB::table('employees')
            ->where('tenant_id', $context['tenantId'])
            ->where('designation', 'Medical Representative')
            ->orderByDesc('id')
            ->limit(max($count, 1))
            ->get(['id', 'branch_id', 'area_id', 'division_id', 'name', 'employee_code', 'phone', 'email']);

        for ($i = 1; $i <= $count; $i++) {
            $employee = $employees[($i - 1) % max($employees->count(), 1)] ?? null;
            $name = $employee?->name ?: $this->personName($i + 20);
            $rows[] = [
                'tenant_id' => $context['tenantId'],
                'company_id' => $context['companyId'],
                'branch_id' => $employee?->branch_id ?? $context['branchIds'][$i % count($context['branchIds'])] ?? null,
                'employee_id' => $employee?->id,
                'area_id' => $employee?->area_id ?? $context['areaIds'][$i % count($context['areaIds'])] ?? null,
                'division_id' => $employee?->division_id ?? $context['divisionIds'][$i % count($context['divisionIds'])] ?? null,
                'name' => $name,
                'employee_code' => $employee?->employee_code ?: 'MR-'.$context['tenantNo'].'-'.$runCode.'-'.$i,
                'phone' => $employee?->phone ?: '98'.str_pad((string) (83000000 + $context['tenantNo'] * 10000 + $i), 8, '0', STR_PAD_LEFT),
                'email' => $employee?->email ?: 'mr'.$context['tenantNo'].'-'.$runCode.'-'.$i.'@demo.pharmanp.test',
                'territory' => $this->places[$i % count($this->places)],
                'monthly_target' => [75000, 100000, 150000, 200000][$i % 4],
                'is_active' => true,
                'created_by' => $context['ownerId'],
                'updated_by' => $context['ownerId'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertChunked('medical_representatives', $rows, $chunk);
    }

    private function seedProducts(array $context, int $count, int $chunk, string $runCode): void
    {
        $rows = [];
        $now = now();

        for ($i = 1; $i <= $count; $i++) {
            $categoryName = $this->categories[$i % count($this->categories)];
            $formulation = $this->formulations[$i % count($this->formulations)];
            $mrp = 20 + ($i % 450);
            $purchase = round($mrp * (0.55 + (($i % 15) / 100)), 2);
            $sku = 'LD-'.$context['tenantNo'].'-'.$runCode.'-'.str_pad((string) $i, 7, '0', STR_PAD_LEFT);

            $rows[] = [
                'tenant_id' => $context['tenantId'],
                'company_id' => $context['companyId'],
                'store_id' => $context['storeId'],
                'category_id' => $context['categoryIds'][$i % count($context['categoryIds'])] ?? null,
                'division_id' => $context['divisionIds'][$i % count($context['divisionIds'])] ?? null,
                'unit_id' => $context['unitIds'][$i % count($context['unitIds'])] ?? null,
                'sku' => $sku,
                'barcode' => 'NP'.str_pad((string) ($context['tenantNo'] * 100000000 + $i), 12, '0', STR_PAD_LEFT),
                'product_code' => $sku,
                'hs_code' => '3004.'.str_pad((string) ($i % 9999), 4, '0', STR_PAD_LEFT),
                'name' => $categoryName.' '.$formulation.' '.$i,
                'generic_name' => $categoryName.' Generic',
                'composition' => $categoryName.' '.$formulation,
                'group_name' => $categoryName,
                'formulation' => $formulation,
                'strength' => ((($i % 5) + 1) * 100).'mg',
                'manufacturer_name' => $this->places[$i % count($this->places)].' Pharma Labs',
                'packaging_type' => ['Strip', 'Box', 'Bottle', 'Vial', 'Tube'][$i % 5],
                'case_movement' => ['Fast', 'Regular', 'Slow', 'Seasonal'][$i % 4],
                'conversion' => 1,
                'rack_location' => 'R'.(($i % 24) + 1).'-S'.(($i % 8) + 1),
                'previous_price' => round($mrp * 0.95, 2),
                'mrp' => $mrp,
                'purchase_price' => $purchase,
                'selling_price' => $mrp,
                'cc_rate' => ($i % 4) * 0.5,
                'discount_percent' => [0, 2, 3, 5][$i % 4],
                'reorder_level' => [10, 20, 30, 50][$i % 4],
                'reorder_quantity' => [50, 100, 150, 200][$i % 4],
                'is_batch_tracked' => true,
                'is_active' => true,
                'keywords' => Str::lower($categoryName.' '.$formulation.' '.$sku),
                'description' => 'Load demo product for '.$categoryName,
                'created_by' => $context['ownerId'],
                'updated_by' => $context['ownerId'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertChunked('products', $rows, $chunk);
    }

    private function seedBatches(array $context, int $count, int $chunk, string $runCode): void
    {
        $products = DB::table('products')
            ->where('tenant_id', $context['tenantId'])
            ->orderByDesc('id')
            ->limit(min(max($count, 1), 50000))
            ->get(['id', 'purchase_price', 'mrp']);
        $suppliers = $this->sampleIds('suppliers', $context['tenantId'], max(1, min(5000, $count)));
        $rows = [];
        $now = now();
        $today = CarbonImmutable::today();

        for ($i = 1; $i <= $count; $i++) {
            $product = $products[($i - 1) % max($products->count(), 1)] ?? null;

            if (! $product) {
                break;
            }

            $quantity = 1000 + ($i % 5000);
            $rows[] = [
                'tenant_id' => $context['tenantId'],
                'company_id' => $context['companyId'],
                'store_id' => $context['storeId'],
                'product_id' => $product->id,
                'supplier_id' => $suppliers[$i % max(count($suppliers), 1)] ?? null,
                'batch_no' => 'LD'.$runCode.'-'.$context['tenantNo'].'-'.$i,
                'barcode' => 'B'.str_pad((string) ($context['tenantNo'] * 100000000 + $i), 12, '0', STR_PAD_LEFT),
                'manufactured_at' => $today->subMonths(2 + ($i % 6))->toDateString(),
                'expires_at' => $today->addDays(45 + ($i % 900))->toDateString(),
                'quantity_received' => $quantity,
                'quantity_available' => $quantity,
                'purchase_price' => $product->purchase_price,
                'mrp' => $product->mrp,
                'is_active' => true,
                'created_by' => $context['ownerId'],
                'updated_by' => $context['ownerId'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertChunked('batches', $rows, $chunk);
    }

    private function seedPurchases(array $context, int $count, int $chunk, string $runCode): void
    {
        $supplierIds = $this->sampleIds('suppliers', $context['tenantId'], 5000);
        $productRows = DB::table('products')->where('tenant_id', $context['tenantId'])->orderByDesc('id')->limit(10000)->get(['id', 'purchase_price', 'mrp']);
        $now = now();
        $today = CarbonImmutable::today();
        $purchaseRows = [];
        $itemRows = [];
        $movementRows = [];
        $accountRows = [];
        $supplierBalances = [];

        for ($i = 1; $i <= $count; $i++) {
            if ($productRows->isEmpty() || empty($supplierIds)) {
                return;
            }

            $supplierId = $supplierIds[$i % count($supplierIds)];
            $purchaseNo = 'PUR-LD-'.$context['tenantNo'].'-'.$runCode.'-'.str_pad((string) $i, 7, '0', STR_PAD_LEFT);
            $date = $today->subDays($i % 365)->toDateString();
            $product = $productRows[$i % $productRows->count()];
            $qty = 10 + ($i % 40);
            $total = round($qty * (float) $product->purchase_price, 2);
            $paid = $i % 3 === 0 ? 0 : round($total * 0.65, 2);

            $purchaseRows[] = [
                'tenant_id' => $context['tenantId'],
                'company_id' => $context['companyId'],
                'store_id' => $context['storeId'],
                'supplier_id' => $supplierId,
                'purchase_no' => $purchaseNo,
                'supplier_invoice_no' => 'SUP-LD-'.$i,
                'purchase_date' => $date,
                'due_date' => $today->subDays($i % 365)->addDays([30, 45, 60, 90][$i % 4])->toDateString(),
                'status' => 'received',
                'payment_status' => $paid <= 0 ? 'unpaid' : ($paid >= $total ? 'paid' : 'partial'),
                'payment_type' => $paid > 0 ? 'payment' : 'credit',
                'subtotal' => $total,
                'discount_total' => 0,
                'grand_total' => $total,
                'paid_amount' => $paid,
                'notes' => 'Load demo purchase',
                'created_by' => $context['ownerId'],
                'updated_by' => $context['ownerId'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $supplierBalances[$supplierId] = ($supplierBalances[$supplierId] ?? 0) + ($total - $paid);

            if (count($purchaseRows) >= $chunk) {
                $this->flushPurchases($context, $purchaseRows, $itemRows, $movementRows, $accountRows, $productRows, $chunk, $runCode);
            }
        }

        $this->flushPurchases($context, $purchaseRows, $itemRows, $movementRows, $accountRows, $productRows, $chunk, $runCode);
        $this->applyBalances('suppliers', $supplierBalances);
    }

    private function flushPurchases(array $context, array &$purchaseRows, array &$itemRows, array &$movementRows, array &$accountRows, mixed $productRows, int $chunk, string $runCode): void
    {
        if ($purchaseRows === []) {
            return;
        }

        $this->insertChunked('purchases', $purchaseRows, $chunk);
        $purchaseIds = DB::table('purchases')->whereIn('purchase_no', array_column($purchaseRows, 'purchase_no'))->pluck('id', 'purchase_no');
        $now = now();

        foreach ($purchaseRows as $index => $purchase) {
            $product = $productRows[$index % $productRows->count()];
            $purchaseId = (int) $purchaseIds[$purchase['purchase_no']];
            $qty = max(1, (int) round((float) $purchase['grand_total'] / max((float) $product->purchase_price, 1)));
            $itemRows[] = [
                'purchase_id' => $purchaseId,
                'product_id' => $product->id,
                'batch_no' => 'PB-LD-'.$runCode.'-'.$purchaseId,
                'quantity' => $qty,
                'free_quantity' => 0,
                'purchase_price' => $product->purchase_price,
                'mrp' => $product->mrp,
                'discount_percent' => 0,
                'discount_amount' => 0,
                'line_total' => $purchase['grand_total'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $movementRows[] = [
                'tenant_id' => $context['tenantId'],
                'company_id' => $context['companyId'],
                'store_id' => $context['storeId'],
                'movement_date' => $purchase['purchase_date'],
                'product_id' => $product->id,
                'movement_type' => 'purchase_receive',
                'quantity_in' => $qty,
                'quantity_out' => 0,
                'source_type' => 'purchase',
                'source_id' => $purchaseId,
                'reference_type' => 'purchase',
                'reference_id' => $purchaseId,
                'notes' => 'Load purchase '.$purchase['purchase_no'],
                'created_by' => $context['ownerId'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $total = (float) $purchase['grand_total'];
            $paid = (float) $purchase['paid_amount'];
            $due = max(round($total - $paid, 2), 0);

            $accountRows[] = $this->accountRow($context, $purchase['purchase_date'], 'inventory', 'purchase', $purchaseId, $total, 0, $context['ownerId']);

            if ($paid > 0) {
                $accountRows[] = $this->accountRow($context, $purchase['purchase_date'], 'cash', 'purchase', $purchaseId, 0, $paid, $context['ownerId']);
            }

            if ($due > 0) {
                $accountRows[] = $this->accountRow($context, $purchase['purchase_date'], 'payable', 'purchase', $purchaseId, 0, $due, $context['ownerId'], 'supplier', (int) $purchase['supplier_id']);
            }
        }

        $this->insertChunked('purchase_items', $itemRows, $chunk);
        $this->insertChunked('stock_movements', $movementRows, $chunk);
        $this->insertChunked('account_transactions', $accountRows, $chunk);

        $purchaseRows = $itemRows = $movementRows = $accountRows = [];
    }

    private function seedSales(array $context, int $count, int $chunk, string $runCode): void
    {
        $customers = $this->sampleIds('customers', $context['tenantId'], 20000);
        $mrs = DB::table('medical_representatives')
            ->where('tenant_id', $context['tenantId'])
            ->orderByDesc('id')
            ->limit(2000)
            ->get(['id', 'employee_id']);
        $batches = DB::table('batches')
            ->where('tenant_id', $context['tenantId'])
            ->where('quantity_available', '>', 0)
            ->orderByDesc('id')
            ->limit(30000)
            ->get(['id', 'product_id', 'mrp']);
        $now = now();
        $today = CarbonImmutable::today();
        $invoiceRows = [];
        $customerBalances = [];

        for ($i = 1; $i <= $count; $i++) {
            if ($batches->isEmpty() || empty($customers)) {
                return;
            }

            $batch = $batches[$i % $batches->count()];
            $customerId = $customers[$i % count($customers)];
            $mrId = empty($mrs) || $i % 5 === 0 ? null : $mrs[$i % count($mrs)];
            $qty = 1 + ($i % 5);
            $total = round($qty * (float) $batch->mrp, 2);
            $paid = $i % 4 === 0 ? 0 : ($i % 7 === 0 ? round($total * 0.5, 2) : $total);
            $invoiceRows[] = [
                'tenant_id' => $context['tenantId'],
                'company_id' => $context['companyId'],
                'store_id' => $context['storeId'],
                'branch_id' => $context['branchIds'][$i % count($context['branchIds'])] ?? null,
                'customer_id' => $customerId,
                'medical_representative_id' => $mrId,
                'invoice_no' => 'SI-LD-'.$context['tenantNo'].'-'.$runCode.'-'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
                'invoice_date' => $today->subDays($i % 365)->toDateString(),
                'due_date' => $today->subDays($i % 365)->addDays([30, 45, 60, 90][$i % 4])->toDateString(),
                'sale_type' => $i % 5 === 0 ? 'pos' : 'retail',
                'status' => 'confirmed',
                'payment_status' => $paid <= 0 ? 'unpaid' : ($paid >= $total ? 'paid' : 'partial'),
                'payment_type' => $paid > 0 ? 'receipt' : 'credit',
                'subtotal' => $total,
                'discount_total' => 0,
                'grand_total' => $total,
                'paid_amount' => $paid,
                'notes' => 'Load demo sale',
                'created_by' => $context['ownerId'],
                'updated_by' => $context['ownerId'],
                'created_at' => $now,
                'updated_at' => $now,
                '_batch_id' => $batch->id,
                '_product_id' => $batch->product_id,
                '_quantity' => $qty,
                '_unit_price' => $batch->mrp,
            ];
            $customerBalances[$customerId] = ($customerBalances[$customerId] ?? 0) + ($total - $paid);

            if (count($invoiceRows) >= $chunk) {
                $this->flushSales($context, $invoiceRows, $chunk);
            }
        }

        $this->flushSales($context, $invoiceRows, $chunk);
        $this->applyBalances('customers', $customerBalances);
    }

    private function flushSales(array $context, array &$invoiceRows, int $chunk): void
    {
        if ($invoiceRows === []) {
            return;
        }

        $private = [];
        $publicRows = [];

        foreach ($invoiceRows as $row) {
            $private[$row['invoice_no']] = [
                'batch_id' => $row['_batch_id'],
                'product_id' => $row['_product_id'],
                'quantity' => $row['_quantity'],
                'unit_price' => $row['_unit_price'],
            ];
            unset($row['_batch_id'], $row['_product_id'], $row['_quantity'], $row['_unit_price']);
            $publicRows[] = $row;
        }

        $this->insertChunked('sales_invoices', $publicRows, $chunk);
        $invoiceIds = DB::table('sales_invoices')->whereIn('invoice_no', array_column($publicRows, 'invoice_no'))->pluck('id', 'invoice_no');
        $now = now();
        $itemRows = [];
        $movementRows = [];
        $accountRows = [];
        $batchOut = [];

        foreach ($publicRows as $invoice) {
            $invoiceId = (int) $invoiceIds[$invoice['invoice_no']];
            $meta = $private[$invoice['invoice_no']];
            $itemRows[] = [
                'sales_invoice_id' => $invoiceId,
                'product_id' => $meta['product_id'],
                'batch_id' => $meta['batch_id'],
                'quantity' => $meta['quantity'],
                'free_quantity' => 0,
                'mrp' => $meta['unit_price'],
                'unit_price' => $meta['unit_price'],
                'discount_percent' => 0,
                'discount_amount' => 0,
                'line_total' => $invoice['grand_total'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $movementRows[] = [
                'tenant_id' => $context['tenantId'],
                'company_id' => $context['companyId'],
                'store_id' => $context['storeId'],
                'movement_date' => $invoice['invoice_date'],
                'product_id' => $meta['product_id'],
                'batch_id' => $meta['batch_id'],
                'movement_type' => 'sales_issue',
                'quantity_in' => 0,
                'quantity_out' => $meta['quantity'],
                'source_type' => 'sales_invoice',
                'source_id' => $invoiceId,
                'reference_type' => 'batch',
                'reference_id' => $meta['batch_id'],
                'notes' => 'Load sale '.$invoice['invoice_no'],
                'created_by' => $context['ownerId'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $batchOut[$meta['batch_id']] = ($batchOut[$meta['batch_id']] ?? 0) + $meta['quantity'];
            $accountRows[] = $this->accountRow($context, $invoice['invoice_date'], 'sales', 'sales_invoice', $invoiceId, 0, (float) $invoice['grand_total'], $context['ownerId']);
            if ((float) $invoice['paid_amount'] > 0) {
                $accountRows[] = $this->accountRow($context, $invoice['invoice_date'], 'cash', 'sales_invoice', $invoiceId, (float) $invoice['paid_amount'], 0, $context['ownerId']);
            }
            $due = (float) $invoice['grand_total'] - (float) $invoice['paid_amount'];
            if ($due > 0) {
                $accountRows[] = $this->accountRow($context, $invoice['invoice_date'], 'receivable', 'sales_invoice', $invoiceId, $due, 0, $context['ownerId'], 'customer', (int) $invoice['customer_id']);
            }
        }

        $this->insertChunked('sales_invoice_items', $itemRows, $chunk);
        $this->insertChunked('stock_movements', $movementRows, $chunk);
        $this->insertChunked('account_transactions', $accountRows, $chunk);

        $this->decrementBatchQuantities($batchOut);

        $invoiceRows = [];
    }

    private function seedRepresentativeVisits(array $context, int $count, int $chunk, string $runCode): void
    {
        $mrs = $this->sampleIds('medical_representatives', $context['tenantId'], 2000);
        $customers = $this->sampleIds('customers', $context['tenantId'], 5000);
        $rows = [];
        $now = now();
        $today = CarbonImmutable::today();

        for ($i = 1; $i <= $count; $i++) {
            if ($mrs->isEmpty()) {
                return;
            }

            $mr = $mrs[$i % max($mrs->count(), 1)] ?? null;
            $rows[] = [
                'tenant_id' => $context['tenantId'],
                'company_id' => $context['companyId'],
                'medical_representative_id' => $mr?->id,
                'employee_id' => $mr?->employee_id,
                'customer_id' => $customers[$i % max(count($customers), 1)] ?? null,
                'visit_date' => $today->subDays($i % 180)->toDateString(),
                'visit_time' => sprintf('%02d:%02d:00', 9 + ($i % 8), ($i * 7) % 60),
                'status' => ['planned', 'visited', 'missed', 'converted'][$i % 4],
                'purpose' => ['Order follow-up', 'Collection', 'New product discussion', 'Expiry return check'][$i % 4],
                'order_value' => $i % 3 === 0 ? (500 + ($i % 1000)) : 0,
                'notes' => 'Load demo MR visit',
                'remarks' => $i % 4 === 0 ? 'Customer requested next follow-up.' : null,
                'location_name' => $this->places[$i % count($this->places)],
                'created_by' => $context['ownerId'],
                'updated_by' => $context['ownerId'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertChunked('representative_visits', $rows, $chunk);
    }

    private function accountRow(array $context, string $date, string $accountType, string $sourceType, int $sourceId, float $debit, float $credit, int $userId, ?string $partyType = null, ?int $partyId = null): array
    {
        return [
            'tenant_id' => $context['tenantId'],
            'company_id' => $context['companyId'],
            'transaction_date' => $date,
            'account_type' => $accountType,
            'party_type' => $partyType,
            'party_id' => $partyId,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'debit' => $debit,
            'credit' => $credit,
            'notes' => 'Load demo posting',
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function sampleIds(string $table, int $tenantId, int $limit): array
    {
        return DB::table($table)
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->limit($limit)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function applyBalances(string $table, array $balances): void
    {
        if (! in_array($table, ['customers', 'suppliers'], true)) {
            throw new \InvalidArgumentException('Unsupported balance table: '.$table);
        }

        $balances = array_filter($balances, fn ($amount) => (float) $amount !== 0.0);

        foreach (array_chunk($balances, 1000, true) as $part) {
            $ids = array_map('intval', array_keys($part));
            $caseSql = 'CASE id ';
            $caseBindings = [];

            foreach ($part as $id => $amount) {
                $caseSql .= 'WHEN ? THEN ? ';
                $caseBindings[] = (int) $id;
                $caseBindings[] = (float) $amount;
            }

            $caseSql .= 'ELSE 0 END';
            $inSql = implode(',', array_fill(0, count($ids), '?'));

            DB::update(
                "UPDATE {$table} SET current_balance = current_balance + ({$caseSql}), updated_at = ? WHERE id IN ({$inSql})",
                [...$caseBindings, now()->toDateTimeString(), ...$ids],
            );
        }
    }

    private function decrementBatchQuantities(array $batchOut): void
    {
        $batchOut = array_filter($batchOut, fn ($quantity) => (float) $quantity !== 0.0);

        foreach (array_chunk($batchOut, 1000, true) as $part) {
            $ids = array_map('intval', array_keys($part));
            $caseSql = 'CASE id ';
            $caseBindings = [];

            foreach ($part as $id => $quantity) {
                $caseSql .= 'WHEN ? THEN ? ';
                $caseBindings[] = (int) $id;
                $caseBindings[] = (float) $quantity;
            }

            $caseSql .= 'ELSE 0 END';
            $inSql = implode(',', array_fill(0, count($ids), '?'));

            DB::update(
                "UPDATE batches SET quantity_available = quantity_available - ({$caseSql}), updated_at = ? WHERE id IN ({$inSql})",
                [...$caseBindings, now()->toDateTimeString(), ...$ids],
            );
        }
    }

    private function insertChunked(string $table, array $rows, int $chunk): void
    {
        $safeChunk = $this->safeInsertChunk($rows, $chunk);

        foreach (array_chunk($rows, $safeChunk) as $part) {
            if ($part !== []) {
                DB::table($table)->insert($part);
            }
        }
    }

    private function safeInsertChunk(array $rows, int $requestedChunk): int
    {
        $firstRow = $rows[0] ?? [];
        $columnCount = max(count($firstRow), 1);
        $placeholderLimit = 60000;

        return max(1, min($requestedChunk, intdiv($placeholderLimit, $columnCount)));
    }

    private function share(int $total, int $parts, int $position): int
    {
        $base = intdiv($total, $parts);
        $remainder = $total % $parts;

        return $base + ($position <= $remainder ? 1 : 0);
    }

    private function ensureOwnerRole(): void
    {
        Role::query()->firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
    }

    private function personName(int $index): string
    {
        return $this->firstNames[$index % count($this->firstNames)].' '.$this->lastNames[$index % count($this->lastNames)];
    }
}
