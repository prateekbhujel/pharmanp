<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $purchase->purchase_no }}</title>
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
            <p>Purchase Invoice</p>
        </div>
        <div class="meta">
            <h2>{{ $purchase->purchase_no }}</h2>
            <p>{{ $purchase->purchase_date?->format('Y-m-d') }}</p>
            <p>{{ $purchase->supplier?->name }}</p>
            @if ($purchase->supplier_invoice_no)
                <p>Supplier Bill: {{ $purchase->supplier_invoice_no }}</p>
            @endif
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th>Batch</th>
                <th>Expiry</th>
                <th class="right">Qty</th>
                <th class="right">Free</th>
                <th class="right">Rate</th>
                <th class="right">Discount</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($purchase->items as $item)
                <tr>
                    <td>{{ $item->product?->name }}</td>
                    <td>{{ $item->batch_no }}</td>
                    <td>{{ $item->expires_at?->format('Y-m-d') }}</td>
                    <td class="right">{{ number_format((float) $item->quantity, 3) }}</td>
                    <td class="right">{{ number_format((float) $item->free_quantity, 3) }}</td>
                    <td class="right">{{ number_format((float) $item->purchase_price, 2) }}</td>
                    <td class="right">{{ number_format((float) $item->discount_amount, 2) }}</td>
                    <td class="right">{{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div><span>Subtotal</span><span>{{ number_format((float) $purchase->subtotal, 2) }}</span></div>
        <div><span>Discount</span><span>{{ number_format((float) $purchase->discount_total, 2) }}</span></div>
        <div class="total"><span>Total</span><span>{{ number_format((float) $purchase->grand_total, 2) }}</span></div>
        <div><span>Paid</span><span>{{ number_format((float) $purchase->paid_amount, 2) }}</span></div>
    </div>
</body>
</html>
