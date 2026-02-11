<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Supplier Ledger</title>
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
            border-bottom: 2px solid #3b82f6;
            padding-bottom: 15px;
        }
        .header h1 {
            margin: 0;
            font-size: 22px;
            color: #3b82f6;
        }
        .header p {
            margin: 5px 0 0;
            color: #666;
        }
        .supplier-info {
            margin-bottom: 20px;
            background: #f9fafb;
            padding: 15px;
            border-radius: 8px;
        }
        .supplier-info table {
            width: 100%;
        }
        .supplier-info td {
            padding: 5px 15px 5px 0;
        }
        .supplier-info .label {
            color: #666;
            font-size: 10px;
        }
        .supplier-info .value {
            font-weight: bold;
            font-size: 12px;
        }
        .summary {
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
        .summary .paid { color: #16a34a; }
        .summary .payable { color: #dc2626; }
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
        .ledger-table .paid { color: #16a34a; }
        .ledger-table .payable { color: #dc2626; }
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
        .badge-purchase { background: #dbeafe; color: #1e40af; }
        .badge-payment { background: #dcfce7; color: #166534; }
        .badge-return { background: #f3e8ff; color: #6b21a8; }
        .badge-completed { background: #dcfce7; color: #166534; }
        .badge-pending { background: #fef3c7; color: #92400e; }
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
        <h1>Supplier Ledger Statement</h1>
        <p>Period: {{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}</p>
    </div>

    <div class="supplier-info">
        <table>
            <tr>
                <td>
                    <div class="label">Supplier Name</div>
                    <div class="value">{{ $supplierData['name'] }}</div>
                </td>
                <td>
                    <div class="label">Supplier Code</div>
                    <div class="value">{{ $supplierData['supplier_code'] ?? 'N/A' }}</div>
                </td>
                <td>
                    <div class="label">Phone</div>
                    <div class="value">{{ $supplierData['phone'] ?? 'N/A' }}</div>
                </td>
                <td>
                    <div class="label">Email</div>
                    <div class="value">{{ $supplierData['email'] ?? 'N/A' }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="summary">
        <table>
            <tr>
                <td>
                    <div class="label">Total Paid</div>
                    <div class="value paid">₹{{ number_format($summary['total_debit'], 2) }}</div>
                </td>
                <td>
                    <div class="label">Total Payable</div>
                    <div class="value payable">₹{{ number_format($summary['total_credit'], 2) }}</div>
                </td>
                <td>
                    <div class="label">Net Amount</div>
                    <div class="value balance">₹{{ number_format($summary['net_amount'], 2) }}</div>
                </td>
                <td>
                    <div class="label">We Owe</div>
                    <div class="value outstanding">₹{{ number_format($summary['current_balance'], 2) }}</div>
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
                <th class="text-right">Paid (₹)</th>
                <th class="text-right">Payable (₹)</th>
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
                        <span class="badge badge-{{ strtolower($entry['type']) }}">{{ $entry['type'] }}</span>
                    </td>
                    <td class="text-right {{ ($entry['paid'] ?? 0) > 0 ? 'paid' : '' }}">
                        {{ ($entry['paid'] ?? 0) > 0 ? number_format($entry['paid'], 2) : '-' }}
                    </td>
                    <td class="text-right {{ ($entry['payable'] ?? 0) > 0 ? 'payable' : '' }}">
                        {{ ($entry['payable'] ?? 0) > 0 ? number_format($entry['payable'], 2) : '-' }}
                    </td>
                    <td class="text-right" style="font-weight: bold;">{{ number_format($entry['balance'], 2) }}</td>
                    <td class="text-center">
                        <span class="badge badge-{{ $entry['status'] }}">{{ ucfirst($entry['status']) }}</span>
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="text-right">Total:</td>
                <td class="text-right paid">{{ number_format($summary['total_debit'], 2) }}</td>
                <td class="text-right payable">{{ number_format($summary['total_credit'], 2) }}</td>
                <td class="text-right">{{ number_format($summary['current_balance'], 2) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        <p>Generated on {{ now()->format('d M Y, h:i A') }}</p>
    </div>
</body>
</html>
