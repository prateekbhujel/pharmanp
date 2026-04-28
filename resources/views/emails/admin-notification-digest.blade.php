<!doctype html>
<html>
<body style="font-family: Arial, sans-serif; color: #172033;">
    <h2 style="color:#1f4f8f;">PharmaNP Daily Notification Digest</h2>
    <p>Generated at {{ $digest['generated_at'] }}</p>

    <h3>Summary</h3>
    <table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse;">
        @foreach(($digest['stats'] ?? []) as $key => $value)
            <tr>
                <th align="left">{{ \Illuminate\Support\Str::of($key)->replace('_', ' ')->title() }}</th>
                <td>{{ is_numeric($value) ? number_format((float) $value, 2) : $value }}</td>
            </tr>
        @endforeach
    </table>

    <h3>Low Stock</h3>
    <ul>
        @forelse(($digest['low_stock_rows'] ?? []) as $row)
            <li>{{ $row['name'] ?? '-' }}: {{ $row['stock_on_hand'] ?? 0 }} in stock, reorder at {{ $row['reorder_level'] ?? 0 }}</li>
        @empty
            <li>No low stock alerts.</li>
        @endforelse
    </ul>

    <h3>Expiry Watch</h3>
    <ul>
        @forelse(($digest['expiry_rows'] ?? []) as $row)
            <li>{{ $row['name'] ?? '-' }} batch {{ $row['batch_no'] ?? '-' }} expires {{ $row['expires_at'] ?? '-' }}</li>
        @empty
            <li>No expiry alerts.</li>
        @endforelse
    </ul>
</body>
</html>
