<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Price List PDF</title>
    <style>
        {{-- DejaVu Sans only: names are shown romanized (Latin script), not
             native Devanagari — dompdf has no complex-script text shaper, so
             Devanagari conjuncts/matras render as tofu boxes regardless of
             which font is embedded. See the $nepaliName comment below. --}}
        body {
            font-family: DejaVu Sans, sans-serif;
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
        th { background: #f9fafb; text-align: left; font-size: 9px; color: #374151; font-weight: 600; }
        td.num { text-align: right; }
        .product-row { background: #f3f4f6; font-weight: 600; }
        .product-cell { padding: 6px; }
        .product-name { font-size: 11px; font-weight: 700; color: #1f2937; }
        .product-nepali { font-size: 10px; color: #6b7280; margin-top: 2px; }
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
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
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
        <div class="kpi">Changed<strong>{{ $changedCount }}</strong></div>
    </div>

    @foreach($priceList as $group)
        @php $grouped = collect($group['items'])->groupBy('product_id'); @endphp

        <div class="category-title">{{ $group['category'] }} ({{ $grouped->count() }} products)</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 28px;">#</th>
                    <th style="width: 120px;">Image</th>
                    <th style="width: 120px;">Product</th>
                    <th style="width: 75px;">Pack</th>
                    <th style="width: 85px;">Wholesale</th>
                    <th style="width: 85px;">MRP</th>
                </tr>
            </thead>
            <tbody>
                @php $sn = 0; @endphp
                @foreach($grouped as $productId => $variants)
                    @php
                        $sn++;
                        $first = collect($variants)->first();
                        $sorted = collect($variants)->sortBy(fn($v) => $v['pack_size_grams'] ?? PHP_INT_MAX)->values();
                        
                        $imageUrl = $first['image_url'] ?? null;
                        $imageSlug = $first['image_slug'] ?? null;
                        $productName = $first['product_name_english'] ?? $first['product_name'] ?? 'Unknown';
                        // Romanized, not native Devanagari: dompdf has no
                        // complex-script text shaper, so Devanagari conjuncts
                        // and matras render as tofu boxes regardless of which
                        // font is embedded. The Latin transliteration is
                        // still readable to a Nepali speaker and renders
                        // correctly. ~92% of active products have one; the
                        // rest just show the English name alone.
                        $nepaliName = $first['product_name_romanized'] ?? null;

                        $localCandidates = [];
                        if (is_string($imageUrl) && $imageUrl !== '' && ! preg_match('/^https?:\/\//i', $imageUrl)) {
                            $localCandidates[] = public_path(ltrim($imageUrl, '/'));
                        }
                        if (is_string($imageSlug) && $imageSlug !== '') {
                            foreach (['webp', 'jpg', 'jpeg', 'png'] as $ext) {
                                $localCandidates[] = public_path('images/productv2-webp/' . $imageSlug . '.' . $ext);
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

                        // Source photos run up to 2048x2048px for the web page's
                        // 64px thumbnail; a PDF embeds a file's actual pixel data
                        // regardless of its CSS box, so reusing them here is what
                        // made this PDF 200MB. Resolve to a small cached copy
                        // instead — see PdfThumbnailService.
                        $resolvedImage = $resolvedLocalImage
                            ? \App\Services\PdfThumbnailService::resolve($resolvedLocalImage)
                            : null;

                        if (! $resolvedImage && is_string($imageUrl) && preg_match('/^https?:\/\//i', $imageUrl)) {
                            // Remote URL, not a local file to thumbnail — used
                            // as-is; none of the current catalogue's photos are
                            // remote, so this path isn't expected to run.
                            $resolvedImage = $imageUrl;
                        }
                    @endphp

                    @foreach($sorted as $index => $item)
                        <tr @if($index === 0) class="product-row" @endif>
                            @if($index === 0)
                                <td rowspan="{{ count($sorted) }}" style="vertical-align: top;">{{ $sn }}</td>
                                <td rowspan="{{ count($sorted) }}" style="vertical-align: top; padding: 3px;">
                                    @if($resolvedImage)
                                        <img src="{{ $resolvedImage }}" alt="{{ $productName }}" class="product-image">
                                    @else
                                        <div class="image-placeholder">No image</div>
                                    @endif
                                </td>
                                <td rowspan="{{ count($sorted) }}" style="vertical-align: top; padding: 4px;">
                                    <div class="product-name">{{ $productName }}</div>
                                    @if($nepaliName)
                                        <div class="product-nepali">{{ $nepaliName }}</div>
                                    @endif
                                </td>
                            @endif
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

