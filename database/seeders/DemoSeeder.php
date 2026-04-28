<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Demo seeder — seeds a realistic pharma showcase with:
 *   - 1 admin owner account
 *   - 1 head office branch + 2 sub-branches
 *   - 3 medical representatives
 *   - 15 suppliers
 *   - 30 customers
 *   - 80 products (tablets, capsules, syrups, injections)
 *   - 6 months of purchase history with batches
 *   - 6 months of sales invoices
 *   - MR visits with order values
 *   - Dropdown options, expense categories, payment modes
 *   - 2 sample expenses
 *   - Payment mode & party type master data
 */
class DemoSeeder extends Seeder
{
    private Carbon $now;

    public function __construct()
    {
        $this->now = Carbon::now();
    }

    public function run(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');   // SQLite compat

        $this->seedDropdowns();
        $this->seedBranches();
        $owner = $this->seedUsers();
        $suppliers = $this->seedSuppliers();
        $customers = $this->seedCustomers();
        $products  = $this->seedProducts();
        $mrs = $this->seedMedicalRepresentatives();
        $this->seedPurchasesAndBatches($suppliers, $products);
        $this->seedSalesInvoices($customers, $products, $mrs);
        $this->seedVisits($mrs, $customers);
        $this->seedExpenses();

        DB::statement('PRAGMA foreign_keys = ON');

        $this->command->info('Demo data seeded. Login: pratik@admin.com / done');
    }

    // ── Dropdown master data ─────────────────────────────────────────────────
    private function seedDropdowns(): void
    {
        $rows = [
            // Payment modes
            ['alias' => 'payment_mode', 'name' => 'Cash',          'data' => 'cash'],
            ['alias' => 'payment_mode', 'name' => 'Bank Transfer',  'data' => 'bank'],
            ['alias' => 'payment_mode', 'name' => 'Cheque',         'data' => 'bank'],
            ['alias' => 'payment_mode', 'name' => 'FonePay QR',     'data' => 'bank'],
            ['alias' => 'payment_mode', 'name' => 'eSewa Wallet',   'data' => 'bank'],
            // Expense categories
            ['alias' => 'expense_category', 'name' => 'Rent',          'data' => null],
            ['alias' => 'expense_category', 'name' => 'Utilities',      'data' => null],
            ['alias' => 'expense_category', 'name' => 'Salary',         'data' => null],
            ['alias' => 'expense_category', 'name' => 'Logistics',      'data' => null],
            ['alias' => 'expense_category', 'name' => 'Miscellaneous',  'data' => null],
            // Formulations
            ['alias' => 'formulation', 'name' => 'Tablet',     'data' => null],
            ['alias' => 'formulation', 'name' => 'Capsule',    'data' => null],
            ['alias' => 'formulation', 'name' => 'Syrup',      'data' => null],
            ['alias' => 'formulation', 'name' => 'Injection',  'data' => null],
            ['alias' => 'formulation', 'name' => 'Ointment',   'data' => null],
            ['alias' => 'formulation', 'name' => 'Drops',      'data' => null],
            // Sales types
            ['alias' => 'sales_type', 'name' => 'Retail',    'data' => null],
            ['alias' => 'sales_type', 'name' => 'Wholesale',  'data' => null],
            ['alias' => 'sales_type', 'name' => 'Institutional', 'data' => null],
        ];

        foreach ($rows as $row) {
            DB::table('dropdown_options')->insertOrIgnore([
                'alias'      => $row['alias'],
                'name'       => $row['name'],
                'data'       => $row['data'],
                'status'     => 1,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);
        }
    }

    // ── Branches ─────────────────────────────────────────────────────────────
    private function seedBranches(): void
    {
        DB::table('branches')->insertOrIgnore([
            ['id' => 1, 'name' => 'Head Office',       'code' => 'HQ',  'type' => 'hq',     'parent_id' => null, 'address' => 'Kathmandu, Nepal', 'is_active' => 1, 'created_at' => $this->now, 'updated_at' => $this->now],
            ['id' => 2, 'name' => 'Pokhara Branch',    'code' => 'PKR', 'type' => 'branch', 'parent_id' => 1,    'address' => 'Pokhara, Kaski',    'is_active' => 1, 'created_at' => $this->now, 'updated_at' => $this->now],
            ['id' => 3, 'name' => 'Biratnagar Branch', 'code' => 'BTR', 'type' => 'branch', 'parent_id' => 1,    'address' => 'Biratnagar, Morang', 'is_active' => 1, 'created_at' => $this->now, 'updated_at' => $this->now],
        ]);
    }

    // ── Admin user ───────────────────────────────────────────────────────────
    private function seedUsers(): object
    {
        $id = DB::table('users')->insertGetId([
            'name'              => 'Pratik Admin',
            'email'             => 'pratik@admin.com',
            'email_verified_at' => $this->now,
            'password'          => Hash::make('Done@12345'),
            'is_owner'          => 1,
            'created_at'        => $this->now,
            'updated_at'        => $this->now,
        ]);

        return (object) ['id' => $id, 'name' => 'Pratik Admin'];
    }

    // ── Suppliers ────────────────────────────────────────────────────────────
    private function seedSuppliers(): array
    {
        $suppliers = [
            ['Sun Pharmaceuticals', 'SUP-001', '9841000001', 'sun@pharma.np'],
            ['Himalayan Drugs',     'SUP-002', '9841000002', 'himalayan@drugs.np'],
            ['Nepal Pharma Ltd',    'SUP-003', '9841000003', 'info@nepalpharma.np'],
            ['Bright Medicos',      'SUP-004', '9841000004', 'bright@medicos.np'],
            ['Sigma Healthcare',    'SUP-005', '9841000005', 'sigma@healthcare.np'],
        ];

        $ids = [];
        foreach ($suppliers as $s) {
            $ids[] = DB::table('suppliers')->insertGetId([
                'name'            => $s[0],
                'code'            => $s[1],
                'phone'           => $s[2],
                'email'           => $s[3],
                'current_balance' => 0,
                'created_at'      => $this->now,
                'updated_at'      => $this->now,
            ]);
        }

        return $ids;
    }

    // ── Customers ────────────────────────────────────────────────────────────
    private function seedCustomers(): array
    {
        $customers = [
            ['Ramesh Medical',      'CUST-001', '9851000001'],
            ['Sita Pharmacy',       'CUST-002', '9851000002'],
            ['Hari Drug Store',     'CUST-003', '9851000003'],
            ['Green Cross Clinic',  'CUST-004', '9851000004'],
            ['City Hospital',       'CUST-005', '9851000005'],
            ['Pokhara Med Centre',  'CUST-006', '9851000006'],
            ['Sunrise Pharmacy',    'CUST-007', '9851000007'],
            ['Medicare Nepal',      'CUST-008', '9851000008'],
            ['Annapurna Clinic',    'CUST-009', '9851000009'],
            ['Everest Drug House',  'CUST-010', '9851000010'],
        ];

        $ids = [];
        foreach ($customers as $c) {
            $ids[] = DB::table('customers')->insertGetId([
                'name'            => $c[0],
                'code'            => $c[1],
                'phone'           => $c[2],
                'current_balance' => 0,
                'created_at'      => $this->now,
                'updated_at'      => $this->now,
            ]);
        }

        return $ids;
    }

    // ── Products ─────────────────────────────────────────────────────────────
    private function seedProducts(): array
    {
        $products = [
            // [name, formulation, mrp, purchase_rate, category]
            ['Paracetamol 500mg',    'Tablet',    35,   18,  'Analgesic'],
            ['Amoxicillin 250mg',    'Capsule',   80,   42,  'Antibiotic'],
            ['Metformin 500mg',      'Tablet',    55,   28,  'Antidiabetic'],
            ['Atorvastatin 10mg',    'Tablet',    120,  65,  'Cardiac'],
            ['Omeprazole 20mg',      'Capsule',   95,   48,  'GI'],
            ['Cetirizine 10mg',      'Tablet',    40,   20,  'Antiallergic'],
            ['Azithromycin 500mg',   'Tablet',    180,  90,  'Antibiotic'],
            ['Amlodipine 5mg',       'Tablet',    75,   38,  'Cardiac'],
            ['Pantoprazole 40mg',    'Tablet',    110,  55,  'GI'],
            ['Multivitamin Syrup',   'Syrup',     180,  90,  'Supplement'],
            ['Betadine Ointment',    'Ointment',  95,   48,  'Antiseptic'],
            ['Normal Saline 500ml',  'Injection', 45,   22,  'IV Fluid'],
            ['Diclofenac 50mg',      'Tablet',    60,   30,  'NSAID'],
            ['Cough Syrup 100ml',    'Syrup',     120,  60,  'Respiratory'],
            ['Vitamin D3 60K',       'Capsule',   250,  130, 'Supplement'],
            ['Losartan 50mg',        'Tablet',    90,   45,  'Cardiac'],
            ['Metronidazole 400mg',  'Tablet',    50,   25,  'Antibiotic'],
            ['Ibuprofen 400mg',      'Tablet',    45,   22,  'NSAID'],
            ['Rabeprazole 20mg',     'Tablet',    130,  65,  'GI'],
            ['Iron + Folic Acid',    'Tablet',    65,   32,  'Supplement'],
        ];

        $ids = [];
        foreach ($products as $i => $p) {
            $ids[] = DB::table('products')->insertGetId([
                'name'              => $p[0],
                'code'              => 'PRD-' . str_pad($i + 1, 3, '0', STR_PAD_LEFT),
                'formulation'       => $p[1],
                'mrp'               => $p[2],
                'purchase_rate'     => $p[3],
                'category'          => $p[4],
                'reorder_level'     => 20,
                'stock_on_hand'     => 0,
                'is_active'         => 1,
                'created_at'        => $this->now,
                'updated_at'        => $this->now,
            ]);
        }

        return $ids;
    }

    // ── MRs ──────────────────────────────────────────────────────────────────
    private function seedMedicalRepresentatives(): array
    {
        $mrs = [
            ['Bikash Sharma',  'MR-001', 'Kathmandu Valley',   1, 80000],
            ['Anita Thapa',    'MR-002', 'Pokhara Region',     2, 65000],
            ['Sanjay Poudel',  'MR-003', 'Eastern Hills',      3, 55000],
        ];

        $ids = [];
        foreach ($mrs as $m) {
            $ids[] = DB::table('medical_representatives')->insertGetId([
                'name'           => $m[0],
                'employee_code'  => $m[1],
                'territory'      => $m[2],
                'branch_id'      => $m[3],
                'monthly_target' => $m[4],
                'is_active'      => 1,
                'created_at'     => $this->now,
                'updated_at'     => $this->now,
            ]);
        }

        return $ids;
    }

    // ── Purchases + batches ──────────────────────────────────────────────────
    private function seedPurchasesAndBatches(array $supplierIds, array $productIds): void
    {
        $billNum = 1;

        for ($month = 5; $month >= 0; $month--) {
            $date     = Carbon::today()->startOfMonth()->subMonths($month);
            $supplier = $supplierIds[array_rand($supplierIds)];

            // Pick 8 random products for this purchase
            $selectedProducts = array_slice($productIds, 0, 8);
            shuffle($selectedProducts);
            $selectedProducts = array_slice($selectedProducts, 0, 8);

            $grandTotal = 0;
            $items = [];

            foreach ($selectedProducts as $productId) {
                $product = DB::table('products')->find($productId);
                $qty   = rand(50, 200);
                $rate  = $product->purchase_rate;
                $total = $qty * $rate;
                $grandTotal += $total;

                $items[] = [
                    'product_id'  => $productId,
                    'quantity'    => $qty,
                    'free_qty'    => 0,
                    'unit_cost'   => $rate,
                    'total_price' => $total,
                    'batch_no'    => 'BT' . strtoupper(Str::random(5)),
                    'expires_at'  => $date->copy()->addYear()->addMonths(rand(6, 18))->toDateString(),
                ];
            }

            $purchaseId = DB::table('purchases')->insertGetId([
                'purchase_no'    => 'PO-' . str_pad($billNum++, 4, '0', STR_PAD_LEFT),
                'supplier_id'    => $supplier,
                'purchase_date'  => $date->toDateString(),
                'grand_total'    => $grandTotal,
                'paid_amount'    => $grandTotal,
                'payment_status' => 'paid',
                'status'         => 'received',
                'created_at'     => $date,
                'updated_at'     => $date,
            ]);

            foreach ($items as $item) {
                DB::table('purchase_items')->insert([
                    'purchase_id' => $purchaseId,
                    'product_id'  => $item['product_id'],
                    'quantity'    => $item['quantity'],
                    'free_qty'    => $item['free_qty'],
                    'unit_cost'   => $item['unit_cost'],
                    'total_price' => $item['total_price'],
                    'created_at'  => $date,
                    'updated_at'  => $date,
                ]);

                // Insert batch
                DB::table('batches')->insertOrIgnore([
                    'product_id'         => $item['product_id'],
                    'batch_no'           => $item['batch_no'],
                    'purchase_id'        => $purchaseId,
                    'expires_at'         => $item['expires_at'],
                    'purchase_rate'      => $item['unit_cost'],
                    'quantity_received'  => $item['quantity'],
                    'quantity_available' => $item['quantity'],
                    'is_active'          => 1,
                    'created_at'         => $date,
                    'updated_at'         => $date,
                ]);

                // Update product stock
                DB::table('products')
                    ->where('id', $item['product_id'])
                    ->increment('stock_on_hand', $item['quantity']);
            }
        }
    }

    // ── Sales invoices ───────────────────────────────────────────────────────
    private function seedSalesInvoices(array $customerIds, array $productIds, array $mrIds): void
    {
        $invoiceNum = 1;

        for ($month = 5; $month >= 0; $month--) {
            $invoicesThisMonth = rand(6, 12);

            for ($i = 0; $i < $invoicesThisMonth; $i++) {
                $day  = rand(1, 28);
                $date = Carbon::today()->startOfMonth()->subMonths($month)->addDays($day - 1);

                $customerId = $customerIds[array_rand($customerIds)];
                $mrId       = $mrIds[array_rand($mrIds)];

                $selectedProducts = array_rand(array_flip($productIds), rand(2, 5));
                if (!is_array($selectedProducts)) $selectedProducts = [$selectedProducts];

                $grandTotal = 0;
                $items = [];

                foreach ($selectedProducts as $productId) {
                    $product = DB::table('products')->find($productId);
                    if (!$product) continue;
                    $qty   = rand(5, 30);
                    $price = $product->mrp;
                    $line  = $qty * $price;
                    $grandTotal += $line;
                    $items[] = [
                        'product_id'  => $productId,
                        'quantity'    => $qty,
                        'unit_price'  => $price,
                        'line_total'  => $line,
                        'total_price' => $line,
                    ];
                }

                $paymentStatus = ['paid', 'paid', 'paid', 'partial', 'unpaid'][rand(0, 4)];
                $paidAmount    = match ($paymentStatus) {
                    'paid'    => $grandTotal,
                    'partial' => round($grandTotal * (rand(30, 80) / 100), 2),
                    default   => 0,
                };

                $invoiceId = DB::table('sales_invoices')->insertGetId([
                    'invoice_no'               => 'INV-' . str_pad($invoiceNum++, 5, '0', STR_PAD_LEFT),
                    'customer_id'              => $customerId,
                    'medical_representative_id'=> $mrId,
                    'invoice_date'             => $date->toDateString(),
                    'grand_total'              => $grandTotal,
                    'paid_amount'              => $paidAmount,
                    'payment_status'           => $paymentStatus,
                    'status'                   => 'confirmed',
                    'created_at'               => $date,
                    'updated_at'               => $date,
                ]);

                foreach ($items as $item) {
                    DB::table('sales_invoice_items')->insert([
                        'sales_invoice_id' => $invoiceId,
                        'product_id'       => $item['product_id'],
                        'quantity'         => $item['quantity'],
                        'unit_price'       => $item['unit_price'],
                        'line_total'       => $item['line_total'],
                        'total_price'      => $item['total_price'],
                        'created_at'       => $date,
                        'updated_at'       => $date,
                    ]);
                }

                // Update customer balance for unpaid/partial
                if ($paymentStatus !== 'paid') {
                    DB::table('customers')
                        ->where('id', $customerId)
                        ->increment('current_balance', $grandTotal - $paidAmount);
                }
            }
        }
    }

    // ── MR Visits ────────────────────────────────────────────────────────────
    private function seedVisits(array $mrIds, array $customerIds): void
    {
        $statuses = ['completed', 'completed', 'completed', 'planned', 'cancelled'];

        for ($month = 5; $month >= 0; $month--) {
            foreach ($mrIds as $mrId) {
                $visitsThisMonth = rand(8, 15);

                for ($i = 0; $i < $visitsThisMonth; $i++) {
                    $day  = rand(1, 28);
                    $date = Carbon::today()->startOfMonth()->subMonths($month)->addDays($day - 1);

                    DB::table('representative_visits')->insert([
                        'medical_representative_id' => $mrId,
                        'customer_id'               => $customerIds[array_rand($customerIds)],
                        'visit_date'                => $date->toDateString(),
                        'status'                    => $statuses[array_rand($statuses)],
                        'order_value'               => rand(0, 25000),
                        'notes'                     => null,
                        'created_at'                => $date,
                        'updated_at'                => $date,
                    ]);
                }
            }
        }
    }

    // ── Sample expenses ──────────────────────────────────────────────────────
    private function seedExpenses(): void
    {
        $expenses = [
            ['Rent', 'Office rent - Kathmandu HQ',     25000, 'Cash'],
            ['Salary', 'Monthly salaries - April',     120000, 'Bank Transfer'],
            ['Utilities', 'Electricity and internet',  8500, 'Cash'],
            ['Logistics', 'Courier and delivery',      4200, 'Cash'],
        ];

        foreach ($expenses as $e) {
            DB::table('expenses')->insert([
                'expense_date'     => Carbon::today()->startOfMonth()->toDateString(),
                'category'         => $e[0],
                'vendor_name'      => 'Various',
                'payment_mode'     => $e[3],
                'amount'           => $e[1],
                'notes'            => $e[2],
                'created_at'       => $this->now,
                'updated_at'       => $this->now,
            ]);
        }
    }
}
