<?php

namespace App\Modules\Party\Http\Controllers;

use App\Http\Controllers\ModularController;
use App\Models\Setting;
use App\Modules\Party\Http\Requests\CustomerLedgerRequest;
use App\Modules\Party\Http\Resources\CustomerLedgerResource;
use App\Modules\Party\Models\Customer;
use App\Modules\Party\Services\CustomerLedgerService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * @OA\Tag(
 *     name="PARTY - Customers and Suppliers",
 *     description="API endpoints for PARTY - Customers and Suppliers"
 * )
 */
class CustomerLedgerController extends ModularController
{
    public function __construct(private readonly CustomerLedgerService $ledger) {}

    /**
     * @OA\Get(
     *     path="/customers/{customer}/ledger",
     *     summary="Api Customers Ledger",
     *     tags={"PARTY - Customers"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function show(CustomerLedgerRequest $request, Customer $customer): JsonResponse
    {
        $payload = (new CustomerLedgerResource($this->ledger->payload(
            $customer,
            $request->user(),
            $request->validated('from'),
            $request->validated('to'),
        )))->resolve($request);

        return $this->success($payload, 'Customer ledger retrieved successfully.')
            ->setData([
                'status' => 'success',
                'code' => 200,
                'message' => 'Customer ledger retrieved successfully.',
                'data' => $payload,
                'summary' => $payload['summary'] ?? [],
                'filters' => $payload['filters'] ?? [],
            ]);
    }

    public function print(CustomerLedgerRequest $request, Customer $customer): View
    {
        return view('prints.customer-ledger', [
            'ledger' => $this->ledger->payload($customer, $request->user(), $request->validated('from'), $request->validated('to')),
            'branding' => Setting::getValue('app.branding', ['app_name' => 'PharmaNP']),
        ]);
    }

    public function pdf(CustomerLedgerRequest $request, Customer $customer)
    {
        return Pdf::loadView('prints.customer-ledger', [
            'ledger' => $this->ledger->payload($customer, $request->user(), $request->validated('from'), $request->validated('to')),
            'branding' => Setting::getValue('app.branding', ['app_name' => 'PharmaNP']),
        ])->setPaper('a4', 'landscape')->stream('customer-ledger-'.$customer->id.'.pdf');
    }
}
