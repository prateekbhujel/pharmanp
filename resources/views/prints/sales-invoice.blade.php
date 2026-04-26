<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_no }}</title>
    <style>
        body { color: #1f2937; font-family: DejaVu Sans, sans-serif; font-size: 11px; margin: 18px; }
        h1, h2, h5, p { margin: 0; }
        .invoice-sheet-top { border-bottom: 1px solid #d1d5db; display: table; padding-bottom: 12px; width: 100%; }
        .invoice-sheet-brand { display: table-cell; vertical-align: top; width: 62%; }
        .invoice-sheet-brand h1 { font-size: 20px; font-weight: 700; text-transform: uppercase; }
        .invoice-sheet-brand p, .muted { color: #64748b; font-size: 11px; }
        .invoice-sheet-ref { display: table-cell; text-align: right; vertical-align: top; width: 38%; }
        .invoice-sheet-ref span { color: #64748b; display: block; font-size: 10px; text-transform: uppercase; }
        .invoice-sheet-ref strong { display: block; font-size: 18px; margin: 2px 0; }
        .invoice-sheet-grid { display: table; margin-top: 12px; width: 100%; }
        .invoice-sheet-box { border: 1px solid #dbe3ee; display: table-cell; padding: 9px; vertical-align: top; width: 48%; }
        .invoice-sheet-box-gap { display: table-cell; width: 4%; }
        .invoice-sheet-box h5 { font-size: 12px; margin-bottom: 6px; text-transform: uppercase; }
        .invoice-sheet-box p { line-height: 1.55; }
        table { border-collapse: collapse; margin-top: 14px; width: 100%; }
        th { background: #1f3a5f; color: #ffffff; font-size: 10px; padding: 6px; text-align: left; }
        td { border-bottom: 1px solid #e5e7eb; padding: 6px; vertical-align: top; }
        tbody tr:nth-child(even) { background: #f8fafc; }
        .right { text-align: right; }
        .totals { border-collapse: collapse; margin-left: auto; margin-top: 12px; width: 250px; }
        .totals td { border-bottom: 0; padding: 5px 7px; }
        .totals .grand td { border-top: 1px solid #111827; font-weight: 700; }
        .footer { border-top: 1px solid #d1d5db; color: #64748b; display: table; margin-top: 18px; padding-top: 9px; width: 100%; }
        .footer span { display: table-cell; }
        .footer span:last-child { text-align: right; }
    </style>
</head>
<body>
    @php
        $appName = $branding['app_name'] ?? 'PharmaNP';
        $dueAmount = max(0, (float) $invoice->grand_total - (float) $invoice->paid_amount);
    @endphp

    <div class="invoice-sheet-top">
        <div class="invoice-sheet-brand">
            <h1>{{ $appName }}</h1>
            <p>Sales invoice copy</p>
            @if (!empty($branding['company_address']))
                <p>{{ $branding['company_address'] }}</p>
            @endif
            @if (!empty($branding['company_phone']) || !empty($branding['company_email']))
                <p>{{ $branding['company_phone'] ?? '' }} {{ !empty($branding['company_email']) ? '| '.$branding['company_email'] : '' }}</p>
            @endif
        </div>
        <div class="invoice-sheet-ref">
            <span>Invoice No</span>
            <strong>{{ $invoice->invoice_no }}</strong>
            <small>Date: {{ $invoice->invoice_date?->format('Y-m-d') }}</small>
        </div>
    </div>

    <div class="invoice-sheet-grid">
        <div class="invoice-sheet-box">
            <h5>Party Details</h5>
            <p><strong>Name:</strong> {{ $invoice->customer?->name ?? 'Walk-in Customer' }}</p>
            <p><strong>Contact:</strong> {{ $invoice->customer?->phone ?? ($invoice->customer?->email ?? '-') }}</p>
            <p><strong>Address:</strong> {{ $invoice->customer?->address ?? '-' }}</p>
        </div>
        <div class="invoice-sheet-box-gap"></div>
        <div class="invoice-sheet-box">
            <h5>Invoice Summary</h5>
            <p><strong>Sale Type:</strong> {{ ucfirst((string) ($invoice->sale_type ?? 'pos')) }}</p>
            <p><strong>Payment:</strong> {{ ucfirst((string) $invoice->payment_status) }}</p>
            <p><strong>MR:</strong> {{ $invoice->medicalRepresentative?->name ?? '-' }}</p>
        </div>
    </div>

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
                <th class="right">Line Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $item->product?->name ?? '-' }}</td>
                    <td>{{ $item->batch?->batch_no ?? '-' }}</td>
                    <td>{{ $item->batch?->expires_at?->format('Y-m-d') ?? '-' }}</td>
                    <td class="right">{{ number_format((float) $item->quantity, 3) }}</td>
                    <td class="right">{{ number_format((float) ($item->free_quantity ?? 0), 3) }}</td>
                    <td class="right">{{ number_format((float) ($item->mrp ?? $item->batch?->mrp ?? $item->product?->mrp ?? 0), 2) }}</td>
                    <td class="right">{{ number_format((float) $item->unit_price, 2) }}</td>
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
        <tr><td>Subtotal</td><td class="right">{{ number_format((float) $invoice->subtotal, 2) }}</td></tr>
        <tr><td>Discount</td><td class="right">{{ number_format((float) $invoice->discount_total, 2) }}</td></tr>
        <tr><td>Paid</td><td class="right">{{ number_format((float) $invoice->paid_amount, 2) }}</td></tr>
        <tr><td>Due</td><td class="right">{{ number_format($dueAmount, 2) }}</td></tr>
        <tr class="grand"><td>Total</td><td class="right">{{ number_format((float) $invoice->grand_total, 2) }}</td></tr>
    </table>

    <div class="footer">
        <span>Printed on {{ now()->format('M j, Y h:i A') }}</span>
        <span>Thank you for your business.</span>
    </div>
</body>
</html>
