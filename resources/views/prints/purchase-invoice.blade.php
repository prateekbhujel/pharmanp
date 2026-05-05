<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $purchase->purchase_no }}</title>
    <style>
        body { color: #1f2937; font-family: DejaVu Sans, sans-serif; font-size: 11px; margin: 18px; }
        h1, h2, h5, p { margin: 0; }
        .invoice-header { border-bottom: 1px solid #d1d5db; padding-bottom: 12px; width: 100%; }
        .invoice-brand h1 { font-size: 20px; font-weight: 700; text-transform: uppercase; }
        .invoice-brand p, .muted { color: #64748b; font-size: 11px; }
        .invoice-ref span { color: #64748b; display: block; font-size: 10px; text-transform: uppercase; }
        .invoice-ref strong { display: block; font-size: 18px; margin: 2px 0; }
        .invoice-grid { margin-top: 12px; width: 100%; }
        .invoice-box { border: 1px solid #dbe3ee; padding: 9px; vertical-align: top; }
        .invoice-box h5 { font-size: 12px; margin-bottom: 6px; text-transform: uppercase; }
        .invoice-box p { line-height: 1.55; }
        table { border-collapse: collapse; margin-top: 14px; width: 100%; }
        th { background: #1f3a5f; color: #ffffff; font-size: 10px; padding: 6px; text-align: left; }
        td { border-bottom: 1px solid #e5e7eb; padding: 6px; vertical-align: top; }
        tbody tr:nth-child(even) { background: #f8fafc; }
        .right { text-align: right; }
        .totals { border-collapse: collapse; float: right; margin-top: 12px; width: 250px; }
        .totals td { border-bottom: 0; padding: 5px 7px; }
        .totals .grand td { border-top: 1px solid #111827; font-weight: 700; }
        .footer { border-top: 1px solid #d1d5db; color: #64748b; margin-top: 30px; padding-top: 9px; width: 100%; }
        .footer-left { float: left; }
        .footer-right { float: right; text-align: right; }
    </style>
</head>
<body>
    @php
        $appName = $branding['app_name'] ?? 'PharmaNP';
        $dueAmount = max(0, (float) $purchase->grand_total - (float) $purchase->paid_amount);
    @endphp

    <table class="invoice-header">
        <tr>
            <td class="invoice-brand" style="border: 0; width: 60%;">
                <h1>{{ $appName }}</h1>
                <p>Purchase invoice copy</p>
                @if (!empty($branding['company_address']))
                    <p>{{ $branding['company_address'] }}</p>
                @endif
                @if (!empty($branding['company_phone']) || !empty($branding['company_email']))
                    <p>{{ $branding['company_phone'] ?? '' }} {{ !empty($branding['company_email']) ? '| '.$branding['company_email'] : '' }}</p>
                @endif
            </td>
            <td class="invoice-ref" style="border: 0; text-align: right; width: 40%;">
                <span>Purchase No</span>
                <strong>{{ $purchase->purchase_no }}</strong>
                <small>Date: {{ $purchase->purchase_date?->format('Y-m-d') }}</small>
                @if ($purchase->supplier_invoice_no)
                    <small style="display:block;">Supplier Bill: {{ $purchase->supplier_invoice_no }}</small>
                @endif
            </td>
        </tr>
    </table>

    <table class="invoice-grid">
        <tr>
            <td class="invoice-box" style="width: 48%;">
                <h5>Supplier Details</h5>
                <p><strong>Name:</strong> {{ $purchase->supplier?->name ?? '-' }}</p>
                <p><strong>Contact:</strong> {{ $purchase->supplier?->phone ?? ($purchase->supplier?->email ?? '-') }}</p>
                <p><strong>Address:</strong> {{ $purchase->supplier?->address ?? '-' }}</p>
            </td>
            <td style="border: 0; width: 4%;"></td>
            <td class="invoice-box" style="width: 48%;">
                <h5>Document Summary</h5>
                <p><strong>Payment:</strong> {{ ucfirst((string) $purchase->payment_status) }}</p>
                <p><strong>Paid:</strong> {{ number_format((float) $purchase->paid_amount, 2) }}</p>
                <p><strong>Due:</strong> {{ number_format($dueAmount, 2) }}</p>
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th style="width: 42px;">S.No</th>
                <th>Product</th>
                <th>Batch</th>
                <th>Expiry</th>
                <th class="right">Qty</th>
                <th class="right">Free</th>
                <th class="right">MRP</th>
                <th class="right">Rate</th>
                <th class="right">CC %</th>
                <th class="right">Disc %</th>
                <th class="right">Free Goods</th>
                <th class="right">Discount</th>
                <th class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($purchase->items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $item->product?->name ?? '-' }}</td>
                    <td>{{ $item->batch_no ?? $item->batch?->batch_no ?? '-' }}</td>
                    <td>{{ ($item->expires_at ?? $item->batch?->expires_at)?->format('Y-m-d') ?? '-' }}</td>
                    <td class="right">{{ number_format((float) $item->quantity, 3) }}</td>
                    <td class="right">{{ number_format((float) $item->free_quantity, 3) }}</td>
                    <td class="right">{{ number_format((float) ($item->mrp ?? $item->batch?->mrp ?? 0), 2) }}</td>
                    <td class="right">{{ number_format((float) $item->purchase_price, 2) }}</td>
                    <td class="right">{{ number_format((float) ($item->cc_rate ?? 0), 2) }}</td>
                    <td class="right">{{ number_format((float) $item->discount_percent, 2) }}</td>
                    <td class="right">{{ number_format((float) ($item->free_goods_value ?? 0), 2) }}</td>
                    <td class="right">{{ number_format((float) $item->discount_amount, 2) }}</td>
                    <td class="right">{{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="right">{{ number_format((float) $purchase->subtotal, 2) }}</td></tr>
        <tr><td>Discount</td><td class="right">{{ number_format((float) $purchase->discount_total, 2) }}</td></tr>
        <tr><td>Paid</td><td class="right">{{ number_format((float) $purchase->paid_amount, 2) }}</td></tr>
        <tr><td>Due</td><td class="right">{{ number_format($dueAmount, 2) }}</td></tr>
        <tr class="grand"><td>Net Payable</td><td class="right">{{ number_format((float) $purchase->grand_total, 2) }}</td></tr>
    </table>

    <div class="footer">
        <span class="footer-left">Printed on {{ now()->format('M j, Y h:i A') }}</span>
        <span class="footer-right">Goods received by stock ledger posting.</span>
    </div>

    <script>window.onload = function() { window.print(); }</script>
</body>
</html>
