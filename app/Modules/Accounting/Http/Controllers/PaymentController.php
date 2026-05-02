<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Models\Setting;
use App\Modules\Accounting\Contracts\PaymentSettlementServiceInterface;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Setup\Models\DropdownOption;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentController
{
    public function __construct(
        private readonly PaymentSettlementServiceInterface $payments,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Payment::query()
            ->with(['customer', 'supplier', 'allocations', 'paymentModeOption:id,name,data'])
            ->when($request->user()?->tenant_id, fn ($builder, $tenantId) => $builder->where('tenant_id', $tenantId))
            ->when($request->boolean('deleted'), fn ($builder) => $builder->onlyTrashed())
            ->when($request->filled('direction'), fn ($builder) => $builder->where('direction', $request->query('direction')))
            ->when($request->filled('party_type'), fn ($builder) => $builder->where('party_type', $request->query('party_type')))
            ->when($request->filled('from'), fn ($builder) => $builder->where('payment_date', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($builder) => $builder->where('payment_date', '<=', $request->query('to')))
            ->latest('payment_date')
            ->latest('id');

        if ($request->filled('search')) {
            $keyword = (string) $request->query('search');
            $query->where(function ($builder) use ($keyword) {
                $builder->where('payment_no', 'like', '%'.$keyword.'%')
                    ->orWhere('reference_no', 'like', '%'.$keyword.'%')
                    ->orWhere('notes', 'like', '%'.$keyword.'%')
                    ->orWhere('payment_mode', 'like', '%'.$keyword.'%');
            });
        }

        $perPage = min(max((int) $request->query('per_page', 20), 5), 100);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'data' => $paginated->getCollection()
                ->map(fn (Payment $payment) => $this->payments->payload($payment))
                ->values(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
            'lookups' => [
                'payment_modes' => DropdownOption::query()
                    ->forAlias('payment_mode')
                    ->active()
                    ->orderBy('name')
                    ->get(['id', 'name', 'data']),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['nullable', 'integer', 'exists:payments,id'],
            'direction' => ['required', Rule::in(['in', 'out'])],
            'party_type' => ['required', Rule::in(['customer', 'supplier'])],
            'party_id' => ['required', 'integer'],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_mode_id' => [
                'required',
                'integer',
                Rule::exists('dropdown_options', 'id')->where(fn ($query) => $query->where('alias', 'payment_mode')),
            ],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'allocations' => ['nullable', 'array'],
            'allocations.*.bill_id' => ['nullable', 'integer'],
            'allocations.*.bill_type' => ['nullable', Rule::in(['sales_invoice', 'purchase'])],
            'allocations.*.allocated_amount' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        $payment = $this->payments->save($validated, $request->user());

        return response()->json([
            'message' => 'Payment saved successfully.',
            'data' => $this->payments->payload($payment, true),
        ]);
    }

    public function outstandingBills(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'party_id' => ['required', 'integer'],
            'party_type' => ['required', Rule::in(['customer', 'supplier'])],
        ]);

        return response()->json([
            'data' => $this->payments->outstandingBills(
                $validated['party_type'],
                (int) $validated['party_id'],
                $request->user(),
            ),
        ]);
    }

    public function show(Payment $payment): JsonResponse
    {
        return response()->json(['data' => $this->payments->payload($payment, true)]);
    }

    public function print(Payment $payment): View
    {
        return view('prints.payment', $this->printData($payment));
    }

    public function pdf(Payment $payment)
    {
        return Pdf::loadView('prints.payment', $this->printData($payment))
            ->setPaper('a4')
            ->stream($payment->payment_no.'.pdf');
    }

    public function destroy(Request $request, Payment $payment): JsonResponse
    {
        $this->payments->delete($payment, $request->user());

        return response()->json(['message' => 'Payment deleted successfully.']);
    }

    private function printData(Payment $payment): array
    {
        return [
            'payment' => $this->payments->payload($payment, true),
            'branding' => Setting::getValue('app.branding', ['app_name' => 'PharmaNP']),
        ];
    }
}
