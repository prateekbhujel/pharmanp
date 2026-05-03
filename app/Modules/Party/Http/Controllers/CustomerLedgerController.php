<?php

namespace App\Modules\Party\Http\Controllers;

use App\Http\Controllers\ModularController;
use App\Models\Setting;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Party\Models\Customer;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesReturn;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * @OA\Tag(
 *     name="PARTY - Customers and Suppliers",
 *     description="API endpoints for PARTY - Customers and Suppliers"
 * )
 */
class CustomerLedgerController extends ModularController
{
    // Return customer ledger: invoices, returns, payments, and balance.
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
    public function show(Request $request, Customer $customer): JsonResponse
    {
        return response()->json($this->ledgerPayload($request, $customer));
    }

    public function print(Request $request, Customer $customer): View
    {
        return view('prints.customer-ledger', [
            'ledger' => $this->ledgerPayload($request, $customer),
            'branding' => Setting::getValue('app.branding', ['app_name' => 'PharmaNP']),
        ]);
    }

    public function pdf(Request $request, Customer $customer)
    {
        return Pdf::loadView('prints.customer-ledger', [
            'ledger' => $this->ledgerPayload($request, $customer),
            'branding' => Setting::getValue('app.branding', ['app_name' => 'PharmaNP']),
        ])->setPaper('a4', 'landscape')->stream('customer-ledger-'.$customer->id.'.pdf');
    }

    private function ledgerPayload(Request $request, Customer $customer): array
    {
        $from = $request->input('from');
        $to = $request->input('to');

        // Invoices
        $invoiceQuery = SalesInvoice::query()
            ->where('customer_id', $customer->id)
            ->latest('invoice_date');

        if ($from) {
            $invoiceQuery->where('invoice_date', '>=', $from);
        }

        if ($to) {
            $invoiceQuery->where('invoice_date', '<=', $to);
        }

        $invoices = $invoiceQuery->get()->map(fn (SalesInvoice $invoice) => [
            'id' => $invoice->id,
            'invoice_no' => $invoice->invoice_no,
            'date' => $invoice->invoice_date->format('M j, Y'),
            'grand_total' => round((float) $invoice->grand_total, 2),
            'paid_amount' => round((float) $invoice->paid_amount, 2),
            'due' => round(max(0, (float) $invoice->grand_total - (float) $invoice->paid_amount), 2),
            'payment_status' => $invoice->payment_status,
        ]);

        // Returns
        $returnQuery = SalesReturn::query()
            ->where('customer_id', $customer->id)
            ->latest('return_date');

        if ($from) {
            $returnQuery->where('return_date', '>=', $from);
        }

        if ($to) {
            $returnQuery->where('return_date', '<=', $to);
        }

        $returns = $returnQuery->get()->map(fn (SalesReturn $return) => [
            'id' => $return->id,
            'return_no' => $return->return_no,
            'date' => $return->return_date->format('M j, Y'),
            'total_amount' => round((float) $return->total_amount, 2),
            'invoice_no' => $return->invoice?->invoice_no ?? '-',
        ]);

        // Payments
        $paymentQuery = Payment::query()
            ->where('party_type', 'customer')
            ->where('party_id', $customer->id)
            ->latest('payment_date');

        if ($from) {
            $paymentQuery->where('payment_date', '>=', $from);
        }

        if ($to) {
            $paymentQuery->where('payment_date', '<=', $to);
        }

        $payments = $paymentQuery->get()->map(fn (Payment $payment) => [
            'id' => $payment->id,
            'payment_no' => $payment->payment_no,
            'date' => $payment->payment_date->format('M j, Y'),
            'direction' => $payment->direction,
            'amount' => round((float) $payment->amount, 2),
            'payment_mode' => $payment->payment_mode,
        ]);

        // Balance summary
        $totalInvoiced = SalesInvoice::query()->where('customer_id', $customer->id)->sum('grand_total');
        $totalReturned = SalesReturn::query()->where('customer_id', $customer->id)->sum('total_amount');
        $totalPaid = SalesInvoice::query()->where('customer_id', $customer->id)->sum('paid_amount');
        $totalPayments = Payment::query()->where('party_type', 'customer')->where('party_id', $customer->id)->where('direction', 'in')->sum('amount');

        return [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'address' => $customer->address,
                'current_balance' => round((float) $customer->current_balance, 2),
            ],
            'invoices' => $invoices,
            'returns' => $returns,
            'payments' => $payments,
            'summary' => [
                'total_invoiced' => round((float) $totalInvoiced, 2),
                'total_returned' => round((float) $totalReturned, 2),
                'total_paid' => round((float) $totalPaid, 2),
                'total_payments' => round((float) $totalPayments, 2),
                'balance' => round((float) $customer->current_balance, 2),
            ],
            'filters' => [
                'from' => $from,
                'to' => $to,
            ],
        ];
    }
}
