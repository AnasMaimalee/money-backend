<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: DejaVu Sans;
            font-size: 12px;
            color: #222;
        }

        .header {
            text-align: center;
            margin-bottom: 15px;
        }

        .header h2 {
            margin: 0;
        }

        .meta {
            margin-bottom: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th, td {
            border: 1px solid #999;
            padding: 6px;
            text-align: left;
        }

        th {
            background: #f1f1f1;
        }

        .credit { color: green; }
        .debit { color: red; }
    </style>
</head>
<body>

<div class="header">
    <h2>{{ $company }} – Wallet Transactions</h2>
    <small>Generated: {{ $generatedAt->format('Y-m-d H:i') }}</small>
</div>

<div class="meta">
    @if($user)
        <strong>User:</strong> {{ $user->name }} ({{ $user->email }})<br>
    @endif
</div>

<table>
    <thead>
    <tr>
        <th>#</th>
        <th>Type</th>
        <th>Amount</th>
        <th>Before</th>
        <th>After</th>
        <th>Reference</th>
        <th>Date</th>
    </tr>
    </thead>
    <tbody>
    @foreach($transactions as $tx)
        <tr>
            <td>{{ $loop->iteration }}</td>
            <td class="{{ $tx->type }}">
                {{ strtoupper($tx->type) }}
            </td>
            <td>₦{{ number_format($tx->amount, 2) }}</td>
            <td>₦{{ number_format($tx->balance_before, 2) }}</td>
            <td>₦{{ number_format($tx->balance_after, 2) }}</td>
            <td>{{ $tx->reference }}</td>
            <td>{{ $tx->created_at->format('Y-m-d H:i') }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

</body>
</html>
