<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $purchaseReturn->return_no }}</title>
    <style>
        body { color: #1f2937; font-family: DejaVu Sans, sans-serif; font-size: 11px; margin: 18px; }
        h1, h2, h5, p { margin: 0; }
        .invoice-sheet-top { border-bottom: 1px solid #d1d5db; padding-bottom: 12px; width: 100%; clear: both; overflow: hidden; }
        .invoice-sheet-brand { float: left; width: 60%; }
        .invoice-sheet-brand h1 { font-size: 20px; font-weight: 700; text-transform: uppercase; }
        .invoice-sheet-brand p, .muted { color: #64748b; font-size: 11px; }
        .invoice-sheet-ref { float: right; text-align: right; width: 35%; }
        .invoice-sheet-ref span { color: #64748b; display: block; font-size: 10px; text-transform: uppercase; }
        .invoice-sheet-ref strong { display: block; font-size: 18px; margin: 2px 0; }
        .invoice-sheet-grid { margin-top: 12px; width: 100%; clear: both; overflow: hidden; }
        .invoice-sheet-box { border: 1px solid #dbe3ee; float: left; padding: 9px; width: 45%; }
        .invoice-sheet-box-gap { float: left; width: 4%; height: 10px; }
        .invoice-sheet-box h5 { font-size: 12px; margin-bottom: 6px; text-transform: uppercase; }
        .invoice-sheet-box p { line-height: 1.55; }
        table { border-collapse: collapse; margin-top: 14px; width: 100%; clear: both; }
        th { background: #1f3a5f; color: #ffffff; font-size: 10px; padding: 6px; text-align: left; }
        td { border-bottom: 1px solid #e5e7eb; padding: 6px; vertical-align: top; }
        tbody tr:nth-child(even) { background: #f8fafc; }
        .right { text-align: right; }
        .totals { border-collapse: collapse; float: right; margin-top: 12px; width: 250px; }
        .totals td { border-bottom: 0; padding: 5px 7px; }
        .totals .grand td { border-top: 1px solid #111827; font-weight: 700; }
        .footer { border-top: 1px solid #d1d5db; color: #64748b; margin-top: 30px; padding-top: 9px; width: 100%; clear: both; overflow: hidden; }
        .footer span { float: left; }
        .footer span:last-child { float: right; text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h2>{{ $branding['app_name'] ?? 'PharmaNP' }}</h2>
            <div class="muted">Purchase Return</div>
        </div>
        <div class="title">
            {{ $purchaseReturn->return_no }}<br>
            <span class="muted">{{ $purchaseReturn->return_date?->toDateString() }}</span>
        </div>
    </div>

    <p>
        <strong>Supplier:</strong> {{ $purchaseReturn->supplier?->name ?? '-' }}<br>
        <strong>Purchase:</strong> {{ $purchaseReturn->purchase?->purchase_no ?? 'Manual product/batch return' }}<br>
        <strong>Notes:</strong> {{ $purchaseReturn->notes ?: '-' }}
    </p>

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
