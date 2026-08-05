@php
    /**
     * Formats a summary value by what its key means, so money reads as money
     * and a percentage does not get a rupee sign.
     */
    $formatValue = function ($key, $value) {
        if (! is_numeric($value)) {
            return $value;
        }

        if (str_contains($key, 'percent') || str_contains($key, 'margin')) {
            return number_format($value, 1).'%';
        }

        $money = ['revenue', 'amount', 'value', 'profit', 'outstanding', 'sale', 'total'];

        foreach ($money as $needle) {
            if (str_contains($key, $needle)) {
                return '₹'.number_format($value, 2);
            }
        }

        return number_format($value);
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; padding: 0; margin: 0; color: #1f2937; font-size: 12px; }
        .header { background: #1f6f43; color: #fff; padding: 18px 24px; }
        .header h1 { margin: 0 0 4px; font-size: 20px; }
        .header p { margin: 0; font-size: 12px; opacity: 0.85; }
        .content { padding: 20px 24px; }
        h2 { font-size: 14px; margin: 22px 0 8px; color: #1f6f43; border-bottom: 1px solid #d5ded8; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin: 6px 0 12px; }
        th { background: #f1f4f2; padding: 7px 8px; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; color: #55635b; }
        td { padding: 7px 8px; border-top: 1px solid #e5e9e6; }
        td.num, th.num { text-align: right; }
        .kpis { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .kpis td { width: 25%; border: 1px solid #e5e9e6; padding: 10px; vertical-align: top; }
        .kpis .label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7b72; display: block; margin-bottom: 3px; }
        .kpis .value { font-size: 15px; font-weight: bold; color: #14301f; }
        .muted { color: #6b7b72; font-style: italic; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $reportData['title'] }}</h1>
        <p>{{ $reportData['period'] }} &nbsp;·&nbsp; generated {{ now()->format('d M Y, g:i A') }}</p>
    </div>

    <div class="content">
        @if(!empty($reportData['summary']))
            <h2>Summary</h2>
            @php $entries = collect($reportData['summary'])->chunk(4); @endphp
            <table class="kpis">
                @foreach($entries as $row)
                    <tr>
                        @foreach($row as $key => $value)
                            <td>
                                <span class="label">{{ ucwords(str_replace('_', ' ', $key)) }}</span>
                                <span class="value">{{ $formatValue($key, $value) }}</span>
                            </td>
                        @endforeach
                        @for($i = count($row); $i < 4; $i++)<td></td>@endfor
                    </tr>
                @endforeach
            </table>
        @endif

        @if(!empty($reportData['top_products']))
            <h2>Best sellers</h2>
            <table>
                <thead>
                    <tr><th>Product</th><th class="num">Units</th><th class="num">Revenue</th></tr>
                </thead>
                <tbody>
                    @foreach($reportData['top_products'] as $product)
                        <tr>
                            <td>{{ $product['name'] }}</td>
                            <td class="num">{{ number_format($product['quantity']) }}</td>
                            <td class="num">₹{{ number_format($product['revenue'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if(!empty($reportData['low_stock']))
            <h2>Running low</h2>
            <table>
                <thead>
                    <tr><th>Item</th><th class="num">Left</th></tr>
                </thead>
                <tbody>
                    @foreach($reportData['low_stock'] as $item)
                        <tr>
                            <td>{{ $item['name'] }}</td>
                            <td class="num">{{ number_format($item['quantity']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if(!empty($reportData['sales']) && count($reportData['sales']) > 0)
            <h2>Sales</h2>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Invoice</th>
                        <th>Customer</th>
                        <th>Cashier</th>
                        <th class="num">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($reportData['sales'] as $sale)
                        <tr>
                            <td>{{ $sale->created_at?->format('d M') }}</td>
                            <td>{{ $sale->invoice_number }}</td>
                            <td>{{ $sale->customer?->name ?? 'Walk-in' }}</td>
                            <td>{{ $sale->cashier?->name ?? '—' }}</td>
                            <td class="num">₹{{ number_format($sale->total_amount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @elseif(($reportData['record_count'] ?? 0) === 0)
            <p class="muted">No sales were recorded in this period.</p>
        @endif

        @if(!empty($reportData['variants']))
            <h2>Inventory</h2>
            <table>
                <thead>
                    <tr><th>Product</th><th>SKU</th><th class="num">Stock</th></tr>
                </thead>
                <tbody>
                    @foreach($reportData['variants'] as $variant)
                        <tr>
                            <td>{{ $variant->product?->name }}</td>
                            <td>{{ $variant->sku }}</td>
                            <td class="num">{{ number_format($variant->stock_quantity ?? 0) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</body>
</html>
