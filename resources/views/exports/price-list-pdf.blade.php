<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Price List PDF</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; }
        h1 { margin: 0 0 4px 0; font-size: 18px; }
        .meta { margin-bottom: 10px; color: #4b5563; font-size: 10px; }
        .kpi-wrap { margin-bottom: 12px; }
        .kpi { display: inline-block; margin-right: 18px; font-size: 10px; color: #4b5563; }
        .kpi strong { display: block; color: #111827; font-size: 14px; margin-top: 2px; }
        .category-title { background: #eef2ff; color: #3730a3; font-weight: bold; padding: 6px 8px; margin-top: 12px; border: 1px solid #dbeafe; }
        table { width: 100%; border-collapse: collapse; margin-top: 0; }
        th, td { border: 1px solid #e5e7eb; padding: 6px 7px; }
        th { background: #f9fafb; text-align: left; font-size: 10px; color: #374151; }
        td.num { text-align: right; }
        .product-row { background: #f3f4f6; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Product Price List</h1>
    <div class="meta">Generated: {{ $generatedAt }}</div>

    <div class="kpi-wrap">
        <div class="kpi">Products<strong>{{ $uniqueProductCount }}</strong></div>
        <div class="kpi">Variants<strong>{{ $variantCount }}</strong></div>
        <div class="kpi">Changed Prices<strong>{{ $changedCount }}</strong></div>
    </div>

    @foreach($priceList as $group)
        @php $grouped = collect($group['items'])->groupBy('product_name'); @endphp

        <div class="category-title">{{ $group['category'] }} ({{ $grouped->count() }} products)</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 40px;">#</th>
                    <th>Pack Size</th>
                    <th style="width: 110px;">Wholesale (NPR)</th>
                    <th style="width: 90px;">MRP (NPR)</th>
                </tr>
            </thead>
            <tbody>
                @php $sn = 0; @endphp
                @foreach($grouped as $productName => $variants)
                    @php
                        $sn++;
                        $sorted = collect($variants)->sortBy(fn($v) => $v['pack_size_grams'] ?? PHP_INT_MAX)->values();
                    @endphp
                    <tr class="product-row">
                        <td>{{ $sn }}</td>
                        <td colspan="3">{{ $productName }}</td>
                    </tr>
                    @foreach($sorted as $item)
                        <tr>
                            <td></td>
                            <td>{{ $item['pack_size'] }}</td>
                            <td class="num">{{ number_format((float) ($item['wholesale'] ?? 0), 0) }}</td>
                            <td class="num">{{ number_format((float) ($item['mrp'] ?? 0), 0) }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    @endforeach
</body>
</html>
