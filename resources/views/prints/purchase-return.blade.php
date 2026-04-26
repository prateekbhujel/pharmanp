<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $purchaseReturn->return_no }}</title>
    <style>
        body { color: #111827; font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; margin: 24px; }
        .header { display: flex; justify-content: space-between; margin-bottom: 18px; }
        .title { font-size: 22px; font-weight: 700; text-align: right; text-transform: uppercase; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #d1d5db; padding: 7px; text-align: left; }
        th { background: #f3f4f6; }
        .right { text-align: right; }
        .muted { color: #64748b; }
        .totals { margin-left: auto; margin-top: 14px; width: 280px; }
        .totals td { border: 0; border-bottom: 1px solid #e5e7eb; }
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
</body>
</html>
