<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $payment['payment_no'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #172033; font-size: 12px; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #2563eb; padding-bottom: 12px; margin-bottom: 16px; }
        .brand { font-size: 20px; font-weight: 700; color: #1f4f8f; }
        .muted { color: #64748b; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 999px; background: #e0f2fe; color: #075985; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #e2e8f0; padding: 8px; text-align: left; }
        th { background: #f8fafc; }
        .right { text-align: right; }
        .total { margin-top: 16px; text-align: right; font-size: 16px; font-weight: 700; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <div class="brand">{{ $branding['app_name'] ?? 'PharmaNP' }}</div>
            <div class="muted">Payment Receipt / Voucher</div>
        </div>
        <div style="text-align:right">
            <div><strong>{{ $payment['payment_no'] }}</strong></div>
            <div>{{ $payment['payment_date_display'] }}</div>
            <div class="badge">{{ $payment['direction_label'] }}</div>
        </div>
    </div>

    <table>
        <tr><th>Party</th><td>{{ $payment['party_name'] }}</td><th>Mode</th><td>{{ $payment['payment_mode'] }}</td></tr>
        <tr><th>Reference</th><td>{{ $payment['reference_no'] ?: '-' }}</td><th>Notes</th><td>{{ $payment['notes'] ?: '-' }}</td></tr>
    </table>

    <h3>Bill Allocations</h3>
    <table>
        <thead>
            <tr>
                <th>Bill</th>
                <th>Date</th>
                <th class="right">Bill Total</th>
                <th class="right">Allocated</th>
            </tr>
        </thead>
        <tbody>
            @forelse($payment['allocations'] as $allocation)
                <tr>
                    <td>{{ $allocation['bill_number'] }}</td>
                    <td>{{ $allocation['bill_date'] }}</td>
                    <td class="right">{{ number_format((float) $allocation['net_amount'], 2) }}</td>
                    <td class="right">{{ number_format((float) $allocation['allocated_amount'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">On-account payment. No bill allocation.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="total">Amount: {{ number_format((float) $payment['amount'], 2) }}</div>

    <script>window.onload = function() { window.print(); }</script>
</body>
</html>
