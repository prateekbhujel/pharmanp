<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Core\DTOs\TableQueryData;
use App\Core\Support\ApiResponse;
use App\Http\Controllers\ModularController;
use App\Models\Setting;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Services\PaymentSettlementService;
use App\Modules\Setup\Models\DropdownOption;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * @OA\Tag(
 *     name="ACCOUNTING - Finance",
 *     description="API endpoints for ACCOUNTING - Finance"
 * )
 */
class PaymentController extends ModularController
{
    public function __construct(
        private readonly PaymentSettlementService $payments,
    ) {}

    /**
     * @OA\Get(
     *     path="/accounting/payments",
     *     summary="Api Accounting Payments Index",
     *     tags={"ACCOUNTING - Payments"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
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

        $perPage = TableQueryData::perPageFromRequest($request);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'data' => $paginated->getCollection()
                ->map(fn (Payment $payment) => $this->payments->payload($payment))
                ->values(),
            'meta' => ApiResponse::paginationMeta($paginated),
            'lookups' => [
                'payment_modes' => DropdownOption::query()
                    ->forAlias('payment_mode')
                    ->active()
                    ->orderBy('name')
                    ->get(['id', 'name', 'data']),
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/accounting/payments",
     *     summary="Api Accounting Payments Store",
     *     tags={"ACCOUNTING - Payments"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(required=false, @OA\JsonContent(type="object", additionalProperties=true)),
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/accounting/payments/outstanding-bills",
     *     summary="Api Accounting Payments Outstanding Bills",
     *     tags={"ACCOUNTING - Payments"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/accounting/payments/{payment}",
     *     summary="Api Accounting Payments Show",
     *     tags={"ACCOUNTING - Payments"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
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

    /**
     * @OA\Delete(
     *     path="/accounting/payments/{payment}",
     *     summary="Api Accounting Payments Destroy",
     *     tags={"ACCOUNTING - Payments"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
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
