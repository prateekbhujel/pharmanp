<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_no }}</title>
    <style>
        body { color: #111827; font-family: DejaVu Sans, sans-serif; font-size: 12px; margin: 24px; }
        h1, h2, p { margin: 0; }
        .header { border-bottom: 1px solid #d1d5db; display: flex; justify-content: space-between; padding-bottom: 12px; }
        .brand h1 { font-size: 22px; }
        .meta { text-align: right; }
        table { border-collapse: collapse; margin-top: 18px; width: 100%; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 8px; text-align: left; }
        th { background: #f3f4f6; }
        .right { text-align: right; }
        .totals { margin-left: auto; margin-top: 18px; width: 240px; }
        .totals div { display: flex; justify-content: space-between; padding: 5px 0; }
        .total { border-top: 1px solid #111827; font-weight: 700; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">
            <h1>{{ $branding['app_name'] ?? 'PharmaNP' }}</h1>
            <p>Sales Invoice</p>
        </div>
        <div class="meta">
            <h2>{{ $invoice->invoice_no }}</h2>
            <p>{{ $invoice->invoice_date?->format('Y-m-d') }}</p>
            <p>{{ $invoice->customer?->name ?? 'Walk-in Customer' }}</p>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th>Batch</th>
                <th class="right">Qty</th>
                <th class="right">Rate</th>
                <th class="right">Discount</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>{{ $item->product?->name }}</td>
                    <td>{{ $item->batch?->batch_no }}</td>
                    <td class="right">{{ number_format((float) $item->quantity, 3) }}</td>
                    <td class="right">{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="right">{{ number_format((float) $item->discount_amount, 2) }}</td>
                    <td class="right">{{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div><span>Subtotal</span><span>{{ number_format((float) $invoice->subtotal, 2) }}</span></div>
        <div><span>Discount</span><span>{{ number_format((float) $invoice->discount_total, 2) }}</span></div>
        <div class="total"><span>Total</span><span>{{ number_format((float) $invoice->grand_total, 2) }}</span></div>
        <div><span>Paid</span><span>{{ number_format((float) $invoice->paid_amount, 2) }}</span></div>
    </div>
</body>
</html>
