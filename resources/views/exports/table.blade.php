<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { color: #111827; font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .meta { color: #64748b; margin-bottom: 14px; }
        table { border-collapse: collapse; width: 100%; }
        th { background: #f1f5f9; color: #334155; font-weight: 700; }
        th, td { border: 1px solid #dbe3ee; padding: 7px 8px; text-align: left; vertical-align: top; }
        tr:nth-child(even) td { background: #fbfdff; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">Generated at {{ $generatedAt }}</div>
    <table>
        <thead>
            <tr>
                @foreach ($columns as $column)
                    <th>{{ $column }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($columns as $column)
                        <td>{{ $row[$column] ?? '' }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ max(1, count($columns)) }}">No data</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
