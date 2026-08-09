{{--
    Live preview for the "Set Cost" action.

    Styles are scoped and hand-written on purpose: the admin panel does not
    register a viteTheme(), so it ships Filament's precompiled CSS and most
    Tailwind utilities — and every arbitrary value — simply do not exist here.
--}}
@php
    $changing = collect($rows)->filter(
        fn ($r) => $r['price_new'] !== null && abs($r['price_new'] - $r['price_now']) >= 0.005
    )->count();
@endphp

<div class="scp">
    @if (empty($rows))
        <p class="scp-empty">This product has no active packs to reprice.</p>
    @else
        <table class="scp-table">
            <thead>
                <tr>
                    <th>Pack</th>
                    <th class="scp-num">Cost</th>
                    <th class="scp-num">Price now</th>
                    <th class="scp-num">Price after</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    @php
                        $new = $row['price_new'];
                        $moved = $new !== null && abs($new - $row['price_now']) >= 0.005;
                        $up = $moved && $new > $row['price_now'];
                    @endphp
                    <tr>
                        <td>
                            {{ $row['pack'] }}
                            @if ($row['locked'])
                                <span class="scp-tag scp-tag-lock" title="Price manually locked — left alone">locked</span>
                            @elseif ($row['held'])
                                <span class="scp-tag scp-tag-hold" title="Cheap staple — held at its current price">held</span>
                            @endif
                        </td>
                        <td class="scp-num scp-muted">₹{{ number_format($row['cost_new'], 2) }}</td>
                        <td class="scp-num scp-muted">₹{{ number_format($row['price_now'], 0) }}</td>
                        <td class="scp-num">
                            @if (! $moved)
                                <span class="scp-muted">unchanged</span>
                            @else
                                <span class="scp-new {{ $up ? 'scp-up' : 'scp-down' }}">
                                    ₹{{ number_format($new, 0) }}
                                </span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p class="scp-foot">
            @if ($changing === 0)
                Nothing changes — these packs already match this cost.
            @else
                {{ $changing }} of {{ count($rows) }} pack {{ \Illuminate\Support\Str::plural('price', count($rows)) }}
                will change. Locked and held packs keep their price but still get the new cost recorded.
            @endif
        </p>
    @endif
</div>

<style>
    .scp-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8125rem;
    }
    .scp-table th {
        text-align: left;
        font-weight: 600;
        padding: 0.375rem 0.5rem;
        border-bottom: 1px solid rgb(228 228 231);
        color: rgb(113 113 122);
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    .scp-table td {
        padding: 0.375rem 0.5rem;
        border-bottom: 1px solid rgb(244 244 245);
    }
    .scp-table tr:last-child td {
        border-bottom: 0;
    }
    .scp-num {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }
    .scp-muted {
        color: rgb(113 113 122);
    }
    .scp-new {
        font-weight: 600;
    }
    .scp-up {
        color: rgb(180 83 9);
    }
    .scp-down {
        color: rgb(21 128 61);
    }
    .scp-tag {
        display: inline-block;
        margin-left: 0.375rem;
        padding: 0.0625rem 0.3125rem;
        border-radius: 0.25rem;
        font-size: 0.625rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    .scp-tag-lock {
        background: rgb(244 244 245);
        color: rgb(82 82 91);
    }
    .scp-tag-hold {
        background: rgb(254 243 199);
        color: rgb(146 64 14);
    }
    .scp-foot,
    .scp-empty {
        margin-top: 0.625rem;
        font-size: 0.75rem;
        color: rgb(113 113 122);
        line-height: 1.4;
    }

    .dark .scp-table th {
        border-bottom-color: rgb(63 63 70);
        color: rgb(161 161 170);
    }
    .dark .scp-table td {
        border-bottom-color: rgb(39 39 42);
    }
    .dark .scp-muted,
    .dark .scp-foot,
    .dark .scp-empty {
        color: rgb(161 161 170);
    }
    .dark .scp-up {
        color: rgb(252 211 77);
    }
    .dark .scp-down {
        color: rgb(134 239 172);
    }
    .dark .scp-tag-lock {
        background: rgb(63 63 70);
        color: rgb(212 212 216);
    }
    .dark .scp-tag-hold {
        background: rgb(120 53 15);
        color: rgb(254 243 199);
    }
</style>
