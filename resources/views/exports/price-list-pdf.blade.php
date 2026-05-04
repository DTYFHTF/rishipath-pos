<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Price List PDF</title>
    <style>
        @font-face {
            font-family: 'NotoSansDevanagariLocal';
            font-style: normal;
            font-weight: 400;
            src: url('{{ resource_path('fonts/NotoSansDevanagari-Regular.ttf') }}') format('truetype');
        }

        body {
            font-family: 'NotoSansDevanagariLocal', DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #1f2937;
            line-height: 1.35;
        }

        h1 { margin: 0 0 4px 0; font-size: 16px; }
        .meta { margin-bottom: 8px; color: #4b5563; font-size: 9px; }
        .kpi-wrap { margin-bottom: 8px; }
        .kpi { display: inline-block; margin-right: 16px; font-size: 9px; color: #4b5563; }
        .kpi strong { display: block; color: #111827; font-size: 12px; margin-top: 2px; }
        .category-title { background: #eef2ff; color: #3730a3; font-weight: bold; padding: 5px 7px; margin-top: 10px; border: 1px solid #dbeafe; }
        table { width: 100%; border-collapse: collapse; margin-top: 0; table-layout: fixed; }
        th, td { border: 1px solid #e5e7eb; padding: 5px 6px; vertical-align: middle; }
        th { background: #f9fafb; text-align: left; font-size: 9px; color: #374151; }
        td.num { text-align: right; }
        .product-row { background: #f3f4f6; font-weight: 600; }
        .product-cell { padding: 6px; }
        .product-name { font-size: 11px; font-weight: 700; }
        .product-header { width: 100%; border-collapse: collapse; }
        .product-header td { border: none; padding: 0; vertical-align: top; }
        .image-wrap { width: 120px; }
        .product-image {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border: 1px solid #d1d5db;
            border-radius: 4px;
        }
        .image-placeholder {
            width: 120px;
            height: 120px;
            border: 1px dashed #d1d5db;
            color: #9ca3af;
            text-align: center;
            font-size: 9px;
            line-height: 120px;
        }
        .product-meta { padding-left: 8px !important; }
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
                    <th style="width: 28px;">#</th>
                    <th style="width: 86px;">Pack Size</th>
                    <th style="width: 96px;">Wholesale (NPR)</th>
                    <th style="width: 92px;">MRP (NPR)</th>
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
                        <td colspan="3" class="product-cell">
                            @php
                                $first = $sorted->first();
                                $imageUrl = $first['image_url'] ?? null;
                                $imageSlug = $first['image_slug'] ?? null;

                                $localCandidates = [];
                                if (is_string($imageUrl) && $imageUrl !== '' && ! preg_match('/^https?:\/\//i', $imageUrl)) {
                                    $localCandidates[] = public_path(ltrim($imageUrl, '/'));
                                }
                                if (is_string($imageSlug) && $imageSlug !== '') {
                                    foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
                                        $localCandidates[] = public_path('images/products/' . $imageSlug . '.' . $ext);
                                    }
                                }

                                $resolvedLocalImage = null;
                                foreach ($localCandidates as $path) {
                                    if (is_string($path) && is_file($path)) {
                                        $resolvedLocalImage = $path;
                                        break;
                                    }
                                }

                                $resolvedImage = $resolvedLocalImage;
                                if (! $resolvedImage && is_string($imageUrl) && preg_match('/^https?:\/\//i', $imageUrl)) {
                                    $resolvedImage = $imageUrl;
                                }

                                $cleanProductName = preg_replace('/\s+/u', ' ', trim((string) $productName));
                            @endphp

                            <table class="product-header">
                                <tr>
                                    <td class="image-wrap">
                                        @if($resolvedImage)
                                            <img src="{{ $resolvedImage }}" alt="{{ $cleanProductName }}" class="product-image">
                                        @else
                                            <div class="image-placeholder">No image</div>
                                        @endif
                                    </td>
                                    <td class="product-meta">
                                        <div class="product-name">{{ $cleanProductName }}</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
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
