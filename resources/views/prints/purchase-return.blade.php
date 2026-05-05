<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $purchaseReturn->return_no }}</title>
    <style>
        body { color: #1f2937; font-family: DejaVu Sans, sans-serif; font-size: 11px; margin: 18px; }
        h1, h2, h5, p { margin: 0; }
        .invoice-header { border-bottom: 1px solid #d1d5db; padding-bottom: 12px; width: 100%; }
        .invoice-brand h2 { font-size: 18px; font-weight: 700; text-transform: uppercase; }
        .invoice-brand p, .muted { color: #64748b; font-size: 11px; }
        .invoice-ref strong { display: block; font-size: 16px; margin-bottom: 2px; }
        .invoice-ref small { color: #64748b; display: block; }
        .invoice-grid { margin-top: 12px; width: 100%; }
        .invoice-box { border: 1px solid #dbe3ee; padding: 9px; vertical-align: top; }
        .invoice-box p { line-height: 1.55; }
        table { border-collapse: collapse; margin-top: 14px; width: 100%; }
        th { background: #1f3a5f; color: #ffffff; font-size: 10px; padding: 6px; text-align: left; }
        td { border-bottom: 1px solid #e5e7eb; padding: 6px; vertical-align: top; }
        tbody tr:nth-child(even) { background: #f8fafc; }
        .right { text-align: right; }
        .totals { border-collapse: collapse; float: right; margin-top: 12px; width: 250px; }
        .totals td { border-bottom: 0; padding: 5px 7px; }
        .footer { border-top: 1px solid #d1d5db; color: #64748b; margin-top: 30px; padding-top: 9px; width: 100%; }
    </style>
</head>
<body>
    <table class="invoice-header">
        <tr>
            <td class="invoice-brand" style="border: 0; width: 60%;">
                <h2>{{ $branding['app_name'] ?? 'PharmaNP' }}</h2>
                <p>Purchase Return</p>
            </td>
            <td class="invoice-ref" style="border: 0; text-align: right; width: 40%;">
                <strong>{{ $purchaseReturn->return_no }}</strong>
                <small>Date: {{ $purchaseReturn->return_date?->format('Y-m-d') }}</small>
            </td>
        </tr>
    </table>

    <table class="invoice-grid">
        <tr>
            <td class="invoice-box" style="width: 100%;">
                <p><strong>Supplier:</strong> {{ $purchaseReturn->supplier?->name ?? '-' }}</p>
                <p><strong>Purchase:</strong> {{ $purchaseReturn->purchase?->purchase_no ?? 'Manual product/batch return' }}</p>
                <p><strong>Notes:</strong> {{ $purchaseReturn->notes ?: '-' }}</p>
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th style="width: 40px;">#</th>
                <th>Product</th>
                <th>Batch</th>
                <th class="right">Qty</th>
                <th class="right">Rate</th>
                <th class="right">Disc</th>
                <th class="right">Net Rate</th>
                <th class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($purchaseReturn->items as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $item->product?->name ?? '-' }}</td>
                    <td>{{ $item->batch?->batch_no ?? '-' }}</td>
                    <td class="right">{{ number_format((float) $item->return_qty, 3) }}</td>
                    <td class="right">{{ number_format((float) $item->rate, 2) }}</td>
                    <td class="right">{{ number_format((float) $item->discount_amount, 2) }}</td>
                    <td class="right">{{ number_format((float) $item->net_rate, 2) }}</td>
                    <td class="right">{{ number_format((float) $item->return_amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="right">{{ number_format((float) $purchaseReturn->subtotal, 2) }}</td></tr>
        <tr><td>Discount</td><td class="right">{{ number_format((float) $purchaseReturn->discount_total, 2) }}</td></tr>
        <tr><td><strong>Net Return</strong></td><td class="right"><strong>{{ number_format((float) $purchaseReturn->grand_total, 2) }}</strong></td></tr>
    </table>

    <script>window.onload = function() { window.print(); }</script>
</body>
</html>
