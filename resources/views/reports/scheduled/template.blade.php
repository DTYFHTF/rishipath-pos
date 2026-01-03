<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        .header {
            background: #667eea;
            color: white;
            padding: 20px;
            text-align: center;
        }
        .content {
            padding: 30px;
            background: #f9fafb;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
        }
        th {
            background: #f3f4f6;
            padding: 12px;
            text-align: left;
            font-weight: 600;
        }
        td {
            padding: 12px;
            border-top: 1px solid #e5e7eb;
        }
        .summary {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $reportData['title'] }}</h1>
        <p>{{ $reportData['period'] }}</p>
    </div>
    
    <div class="content">
        @if(isset($reportData['summary']))
        <div class="summary">
            <h2>Summary</h2>
            @foreach($reportData['summary'] as $key => $value)
                <p><strong>{{ ucwords(str_replace('_', ' ', $key)) }}:</strong> 
                @if(is_numeric($value))
                    @if(str_contains($key, 'amount') || str_contains($key, 'value'))
                        ₹{{ number_format($value, 2) }}
                    @else
                        {{ number_format($value) }}
                    @endif
                @else
                    {{ $value }}
                @endif
                </p>
            @endforeach
        </div>
        @endif
        
        @if(isset($reportData['sales']))
            <h2>Sales Details</h2>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Invoice</th>
                        <th>Customer</th>
                        <th>Cashier</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($reportData['sales'] as $sale)
                        <tr>
                            <td>{{ $sale->created_at->format('M d, Y') }}</td>
                            <td>{{ $sale->invoice_number }}</td>
                            <td>{{ $sale->customer?->name ?? 'Walk-in' }}</td>
                            <td>{{ $sale->user->name }}</td>
                            <td>₹{{ number_format($sale->total_amount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        
        @if(isset($reportData['variants']))
            <h2>Inventory Items</h2>
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Stock</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($reportData['variants'] as $variant)
                        <tr>
                            <td>{{ $variant->product->name }}</td>
                            <td>{{ $variant->sku }}</td>
                            <td>{{ $variant->stock_quantity }}</td>
                            <td>₹{{ number_format($variant->stock_quantity * $variant->purchase_price, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</body>
</html>
