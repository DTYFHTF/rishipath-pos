<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Shop Price Sheet</title>
    <style>
        {{-- No product photos, no Devanagari — see price-list-pdf.blade.php's
             note on dompdf's lack of complex-script shaping. Bigger type and
             one row per product instead of one row per pack: this is built
             to be read at a glance behind a counter, not browsed online. --}}
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 13px;
            color: #111827;
        }
        h1 { margin: 0 0 2px 0; font-size: 20px; }
        .meta { margin-bottom: 10px; color: #4b5563; font-size: 11px; }
        .category-title {
            background: #1e3a8a;
            color: #fff;
            font-weight: bold;
            font-size: 14px;
            padding: 7px 10px;
            margin-top: 14px;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 7px 10px; vertical-align: middle; }
        th {
            background: #eef2ff;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #374151;
        }
        tr:nth-child(even) td { background: #f9fafb; }
        td.num { text-align: right; font-weight: bold; }
        .product-name { font-size: 14px; font-weight: bold; }
        .product-alt { font-size: 11px; color: #6b7280; }
        .pack-note { font-size: 9px; color: #9ca3af; font-weight: normal; }
    </style>
</head>
<body>
    <h1>Shop Price Sheet</h1>
    <div class="meta">
        Per-kg reference price &middot; Generated {{ $generatedAt }}
    </div>

    @foreach($priceList as $group)
        @php
            $grouped = collect($group['items'])->groupBy('product_id');
        @endphp

        <div class="category-title">{{ $group['category'] }}</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 30px;">#</th>
                    <th>Product</th>
                    <th style="width: 100px;">Wholesale</th>
                    <th style="width: 100px;">MRP</th>
                </tr>
            </thead>
            <tbody>
                @php $sn = 0; @endphp
                @foreach($grouped as $productId => $variants)
                    @php
                        $sn++;
                        $sorted = collect($variants)->sortBy(fn($v) => $v['pack_size_grams'] ?? -1)->values();
                        // The 1kg pack is the natural reference point for a
                        // per-kilo sheet; when a product doesn't have one
                        // (some premium/small-batch lines top out below 1kg)
                        // fall back to its largest listed pack rather than
                        // leaving the row blank.
                        $reference = $sorted->first(fn($v) => abs(($v['pack_size_grams'] ?? 0) - 1000.0) < 0.01)
                            ?? $sorted->last();
                        $first = $sorted->first();
                        $productName = $first['product_name_english'] ?? $first['product_name'] ?? 'Unknown';
                        $altName = $first['product_name_romanized'] ?? null;
                        $isReferencePack = abs(($reference['pack_size_grams'] ?? 0) - 1000.0) < 0.01;
                    @endphp
                    <tr>
                        <td>{{ $sn }}</td>
                        <td>
                            <span class="product-name">{{ $productName }}</span>
                            @if($altName)
                                <span class="product-alt">&middot; {{ $altName }}</span>
                            @endif
                            @unless($isReferencePack)
                                <span class="pack-note">(no 1kg pack &mdash; showing {{ $reference['pack_size'] ?? '' }})</span>
                            @endunless
                        </td>
                        <td class="num">{{ number_format((float) ($reference['wholesale'] ?? 0), 0) }}</td>
                        <td class="num">{{ number_format((float) ($reference['mrp'] ?? 0), 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach
</body>
</html>
