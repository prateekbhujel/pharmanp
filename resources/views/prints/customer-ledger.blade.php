<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Customer Ledger - {{ $ledger['customer']['name'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #172033; font-size: 11px; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #2563eb; padding-bottom: 10px; margin-bottom: 14px; }
        .brand { font-size: 19px; font-weight: 700; color: #1f4f8f; }
        .muted { color: #64748b; }
        .summary { display: table; width: 100%; margin-bottom: 14px; }
        .summary div { display: table-cell; border: 1px solid #e2e8f0; padding: 8px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0 18px; }
        th, td { border: 1px solid #e2e8f0; padding: 6px; text-align: left; }
        th { background: #f8fafc; }
        .right { text-align: right; }
        h3 { margin: 14px 0 6px; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <div class="brand">{{ $branding['app_name'] ?? 'PharmaNP' }}</div>
            <div class="muted">Customer Ledger</div>
        </div>
        <div style="text-align:right">
            <strong>{{ $ledger['customer']['name'] }}</strong><br>
            {{ $ledger['customer']['phone'] ?: '-' }}<br>
            {{ $ledger['filters']['from'] ?: 'Beginning' }} - {{ $ledger['filters']['to'] ?: 'Today' }}
        </div>
    </div>

    <div class="summary">
        <div><strong>Invoiced</strong><br>{{ number_format((float) $ledger['summary']['total_invoiced'], 2) }}</div>
        <div><strong>Returned</strong><br>{{ number_format((float) $ledger['summary']['total_returned'], 2) }}</div>
        <div><strong>Paid</strong><br>{{ number_format((float) $ledger['summary']['total_paid'], 2) }}</div>
        <div><strong>Balance</strong><br>{{ number_format((float) $ledger['summary']['balance'], 2) }}</div>
    </div>

    <h3>Invoices</h3>
    <table>
        <thead><tr><th>Invoice</th><th>Date</th><th class="right">Total</th><th class="right">Paid</th><th class="right">Due</th><th>Status</th></tr></thead>
        <tbody>
            @forelse($ledger['invoices'] as $row)
                <tr><td>{{ $row['invoice_no'] }}</td><td>{{ $row['date'] }}</td><td class="right">{{ number_format((float) $row['grand_total'], 2) }}</td><td class="right">{{ number_format((float) $row['paid_amount'], 2) }}</td><td class="right">{{ number_format((float) $row['due'], 2) }}</td><td>{{ $row['payment_status'] }}</td></tr>
            @empty
                <tr><td colspan="6">No invoices.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3>Returns</h3>
    <table>
        <thead><tr><th>Return</th><th>Date</th><th>Invoice</th><th class="right">Amount</th></tr></thead>
        <tbody>
            @forelse($ledger['returns'] as $row)
                <tr><td>{{ $row['return_no'] }}</td><td>{{ $row['date'] }}</td><td>{{ $row['invoice_no'] }}</td><td class="right">{{ number_format((float) $row['total_amount'], 2) }}</td></tr>
            @empty
                <tr><td colspan="4">No returns.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3>Payments</h3>
    <table>
        <thead><tr><th>Payment</th><th>Date</th><th>Mode</th><th>Direction</th><th class="right">Amount</th></tr></thead>
        <tbody>
            @forelse($ledger['payments'] as $row)
                <tr><td>{{ $row['payment_no'] }}</td><td>{{ $row['date'] }}</td><td>{{ $row['payment_mode'] }}</td><td>{{ $row['direction'] }}</td><td class="right">{{ number_format((float) $row['amount'], 2) }}</td></tr>
            @empty
                <tr><td colspan="5">No payments.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
