<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Customer Ledger</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #333;
            margin: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #10b981;
            padding-bottom: 15px;
        }
        .header h1 {
            margin: 0;
            font-size: 22px;
            color: #10b981;
        }
        .header p {
            margin: 5px 0 0;
            color: #666;
        }
        .customer-info {
            display: flex;
            margin-bottom: 20px;
            background: #f9fafb;
            padding: 15px;
            border-radius: 8px;
        }
        .customer-info table {
            width: 100%;
        }
        .customer-info td {
            padding: 5px 15px 5px 0;
        }
        .customer-info .label {
            color: #666;
            font-size: 10px;
        }
        .customer-info .value {
            font-weight: bold;
            font-size: 12px;
        }
        .summary {
            display: flex;
            margin-bottom: 20px;
        }
        .summary table {
            width: 100%;
            border-collapse: collapse;
        }
        .summary td {
            text-align: center;
            padding: 10px;
            border: 1px solid #e5e7eb;
        }
        .summary .label {
            color: #666;
            font-size: 10px;
        }
        .summary .value {
            font-size: 16px;
            font-weight: bold;
        }
        .summary .debit { color: #dc2626; }
        .summary .credit { color: #16a34a; }
        .summary .balance { color: #333; }
        .summary .outstanding { color: #f97316; }
        .ledger-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .ledger-table th {
            background: #f3f4f6;
            padding: 10px 8px;
            text-align: left;
            font-weight: bold;
            border-bottom: 2px solid #e5e7eb;
            font-size: 10px;
        }
        .ledger-table td {
            padding: 8px;
            border-bottom: 1px solid #e5e7eb;
        }
        .ledger-table .text-right {
            text-align: right;
        }
        .ledger-table .text-center {
            text-align: center;
        }
        .ledger-table .debit { color: #dc2626; }
        .ledger-table .credit { color: #16a34a; }
        .ledger-table tfoot td {
            font-weight: bold;
            background: #f3f4f6;
            border-top: 2px solid #e5e7eb;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: bold;
        }
        .badge-sale { background: #dbeafe; color: #1e40af; }
        .badge-payment { background: #dcfce7; color: #166534; }
        .badge-completed { background: #dcfce7; color: #166534; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-overdue { background: #fee2e2; color: #991b1b; }
        .footer {
            margin-top: 30px;
            text-align: center;
            color: #999;
            font-size: 9px;
            border-top: 1px solid #e5e7eb;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Customer Ledger Statement</h1>
        <p>Period: {{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}</p>
    </div>

    <div class="customer-info">
        <table>
            <tr>
                <td>
                    <div class="label">Customer Name</div>
                    <div class="value">{{ $customerData['name'] }}</div>
                </td>
                <td>
                    <div class="label">Customer Code</div>
                    <div class="value">{{ $customerData['customer_code'] ?? 'N/A' }}</div>
                </td>
                <td>
                    <div class="label">Phone</div>
                    <div class="value">{{ $customerData['phone'] ?? 'N/A' }}</div>
                </td>
                <td>
                    <div class="label">Email</div>
                    <div class="value">{{ $customerData['email'] ?? 'N/A' }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="summary">
        <table>
            <tr>
                <td>
                    <div class="label">Total Debit</div>
                    <div class="value debit">₹{{ number_format($summary['total_debit'], 2) }}</div>
                </td>
                <td>
                    <div class="label">Total Credit</div>
                    <div class="value credit">₹{{ number_format($summary['total_credit'], 2) }}</div>
                </td>
                <td>
                    <div class="label">Net Amount</div>
                    <div class="value balance">₹{{ number_format($summary['net_amount'], 2) }}</div>
                </td>
                <td>
                    <div class="label">Current Balance</div>
                    <div class="value {{ $summary['current_balance'] > 0 ? 'debit' : 'credit' }}">₹{{ number_format($summary['current_balance'], 2) }}</div>
                </td>
                <td>
                    <div class="label">Outstanding</div>
                    <div class="value outstanding">₹{{ number_format($summary['outstanding'], 2) }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="ledger-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Reference</th>
                <th>Description</th>
                <th>Type</th>
                <th class="text-right">Debit (₹)</th>
                <th class="text-right">Credit (₹)</th>
                <th class="text-right">Balance (₹)</th>
                <th class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($ledgerEntries as $entry)
            <tr>
                <td>{{ $entry['date'] }}</td>
                <td>{{ $entry['reference'] ?? '-' }}</td>
                <td>{{ $entry['description'] }}</td>
                <td>
                    <span class="badge badge-{{ $entry['type'] }}">
                        {{ ucwords(str_replace('_', ' ', $entry['type'])) }}
                    </span>
                </td>
                <td class="text-right {{ $entry['debit'] > 0 ? 'debit' : '' }}">
                    {{ $entry['debit'] > 0 ? number_format($entry['debit'], 2) : '-' }}
                </td>
                <td class="text-right {{ $entry['credit'] > 0 ? 'credit' : '' }}">
                    {{ $entry['credit'] > 0 ? number_format($entry['credit'], 2) : '-' }}
                </td>
                <td class="text-right {{ $entry['balance'] > 0 ? 'debit' : 'credit' }}">
                    {{ number_format($entry['balance'], 2) }}
                </td>
                <td class="text-center">
                    <span class="badge badge-{{ $entry['status'] }}">
                        {{ ucfirst($entry['status']) }}
                    </span>
                </td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="text-right">Total:</td>
                <td class="text-right debit">{{ number_format($summary['total_debit'], 2) }}</td>
                <td class="text-right credit">{{ number_format($summary['total_credit'], 2) }}</td>
                <td class="text-right {{ $summary['current_balance'] > 0 ? 'debit' : 'credit' }}">
                    {{ number_format($summary['current_balance'], 2) }}
                </td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        Generated on {{ now()->format('d M Y H:i:s') }} | Rishipath POS
    </div>
</body>
</html>
