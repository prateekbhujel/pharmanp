<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Core\DTOs\TableQueryData;
use App\Http\Controllers\ModularController;
use App\Modules\Accounting\Http\Requests\OutstandingBillsRequest;
use App\Modules\Accounting\Http\Requests\PaymentRequest;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Services\PaymentSettlementService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        return response()->json($this->payments->table(
            TableQueryData::fromRequest($request, ['deleted', 'direction', 'party_type', 'from', 'to']),
            $request->user(),
        ));
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
    public function store(PaymentRequest $request): JsonResponse
    {
        $payment = $this->payments->save($request->validated(), $request->user());

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
    public function outstandingBills(OutstandingBillsRequest $request): JsonResponse
    {
        $validated = $request->validated();

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
    public function show(Request $request, Payment $payment): JsonResponse
    {
        return response()->json(['data' => $this->payments->payloadForUser($payment, $request->user(), true)]);
    }

    public function print(Request $request, Payment $payment): View
    {
        return view('prints.payment', $this->payments->printPayload($payment, $request->user()));
    }

    public function pdf(Request $request, Payment $payment)
    {
        return Pdf::loadView('prints.payment', $this->payments->printPayload($payment, $request->user()))
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

}
