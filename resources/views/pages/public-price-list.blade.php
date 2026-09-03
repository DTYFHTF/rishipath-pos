{{--
    Public price list. Standalone document rather than a Filament page: this is
    served to anyone holding the unlisted link, with no session and no admin
    assets. Search, sort and category filtering all run client-side over the
    payload below, which is small (a few hundred rows) and already in memory -
    so filtering is instant and there is no endpoint for anyone to hammer.

    Everything here is retail-facing. The cost and wholesale columns are
    stripped server-side in PublicPriceListController, not hidden with CSS.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $organization->name }} — Price List</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; background: #f8f7f4; }
        .num { font-variant-numeric: tabular-nums; }
        /* The toolbar follows the page so search stays reachable in a long list. */
        .toolbar { position: sticky; top: 0; z-index: 40; backdrop-filter: blur(8px); }
        .chip { display: inline-flex; align-items: center; gap: 4px; padding: 5px 12px; border-radius: 999px;
                font-size: 0.78rem; font-weight: 600; white-space: nowrap; cursor: pointer;
                border: 1.5px solid #e5e7eb; background: #fff; color: #374151; transition: all .12s; }
        .chip:hover { border-color: #c7d2fe; }
        .chip.active { background: #4338ca; border-color: #4338ca; color: #fff; }
        .cat-strip { display: flex; gap: 6px; overflow-x: auto; scrollbar-width: none; padding-bottom: 2px; }
        .cat-strip::-webkit-scrollbar { display: none; }
        .card { background: #fff; border: 1px solid #e9e7e2; border-radius: 14px; overflow: hidden; }
        .pack-row:nth-child(odd) { background: #fbfaf8; }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff; }
            .card { break-inside: avoid; border-color: #d1d5db; }
        }
    </style>
</head>
<body class="text-gray-900">

<header class="bg-white border-b border-gray-200">
    <div class="max-w-5xl mx-auto px-4 py-5">
        <h1 class="text-xl sm:text-2xl font-bold tracking-tight">{{ $organization->name }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">
            Product price list
            @if($generatedAt)
                <span class="text-gray-400">·</span>
                <span title="{{ $generatedAt }}">updated {{ \Carbon\Carbon::parse($generatedAt)->diffForHumans() }}</span>
            @endif
        </p>
    </div>
</header>

@if(empty($priceList))
    <div class="max-w-5xl mx-auto px-4 py-16 text-center">
        <p class="text-gray-500">This price list has not been published yet.</p>
        <p class="text-sm text-gray-400 mt-1">Please check back shortly.</p>
    </div>
@else

<div class="toolbar bg-white/95 border-b border-gray-200 no-print">
    <div class="max-w-5xl mx-auto px-4 py-3 space-y-2.5">
        <div class="flex items-center gap-2">
            <div class="relative flex-1 min-w-0">
                <input id="search" type="search" placeholder="Search a product…" autocomplete="off"
                    class="w-full rounded-xl border-2 border-gray-200 focus:border-indigo-500 focus:ring-0 px-4 py-2.5 text-[0.95rem] outline-none">
            </div>
            <select id="sort"
                class="shrink-0 rounded-xl border-2 border-gray-200 focus:border-indigo-500 focus:ring-0 py-2.5 pl-3 pr-9 text-sm font-medium outline-none">
                <option value="category">Sort: Category</option>
                <option value="name">Name (A–Z)</option>
                <option value="name_desc">Name (Z–A)</option>
                <option value="price">Price (low to high)</option>
                <option value="price_desc">Price (high to low)</option>
            </select>
        </div>

        <div class="cat-strip" id="categories">
            <button class="chip active" data-cat="">All</button>
            @foreach($priceList as $group)
                <button class="chip" data-cat="{{ $group['category'] }}">{{ $group['category'] }}</button>
            @endforeach
        </div>

        <p id="result-count" class="text-xs text-gray-500"></p>
    </div>
</div>

<main class="max-w-5xl mx-auto px-4 py-5">
    <div id="results" class="grid grid-cols-1 sm:grid-cols-2 gap-3"></div>

    <p id="empty" class="hidden text-center text-gray-500 py-16">
        No products match that search.
    </p>
</main>

<footer class="max-w-5xl mx-auto px-4 pb-10 pt-2 text-center text-xs text-gray-400">
    Prices are per pack and include applicable taxes. Subject to change without notice.
</footer>

<script>
    // Rendered server-side by PublicPriceListController's allow-list: product
    // name, pack, retail price and photo. No cost or wholesale figure exists
    // in this payload to be revealed by inspecting the page.
    const PRICE_LIST = @json($priceList);
    const PLACEHOLDER = @json(\App\Filament\Pages\PriceListPage::PLACEHOLDER_IMAGE);

    // Flatten to one entry per product, each holding its packs, so a product
    // is one card no matter how many pack sizes it comes in.
    const PRODUCTS = [];
    for (const group of PRICE_LIST) {
        const byName = new Map();
        for (const item of group.items) {
            if (!byName.has(item.product_name)) {
                byName.set(item.product_name, {
                    name: item.product_name,
                    searchText: `${item.product_name} ${item.product_name_english || ''}`.toLowerCase(),
                    category: group.category,
                    image: item.image_src || PLACEHOLDER,
                    packs: [],
                });
            }
            byName.get(item.product_name).packs.push({
                label: item.pack_size,
                grams: item.pack_size_grams,
                mrp: item.mrp,
            });
        }
        for (const product of byName.values()) {
            product.packs.sort((a, b) => (a.grams ?? 0) - (b.grams ?? 0));
            // Sorting by price uses the cheapest pack, which is what a browser
            // scanning for "how much is this" is really comparing.
            product.minPrice = Math.min(...product.packs.map(p => p.mrp));
            PRODUCTS.push(product);
        }
    }

    const els = {
        search: document.getElementById('search'),
        sort: document.getElementById('sort'),
        results: document.getElementById('results'),
        empty: document.getElementById('empty'),
        count: document.getElementById('result-count'),
        categories: document.getElementById('categories'),
    };

    let activeCategory = '';

    const money = n => 'NPR ' + Number(n).toLocaleString('en-IN');
    const escapeHtml = s => String(s).replace(/[&<>"']/g, c =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    function cardHtml(product) {
        const packs = product.packs.map(p => `
            <div class="pack-row flex items-center justify-between px-3 py-1.5 text-sm">
                <span class="text-gray-600">${escapeHtml(p.label)}</span>
                <span class="num font-semibold">${money(p.mrp)}</span>
            </div>`).join('');

        return `
            <article class="card">
                <div class="flex items-center gap-3 p-3 border-b border-gray-100">
                    <img src="${escapeHtml(product.image)}" alt="${escapeHtml(product.name)}" loading="lazy"
                         onerror="this.onerror=null;this.src='${PLACEHOLDER}';this.classList.add('opacity-50')"
                         class="w-14 h-14 rounded-lg object-cover bg-gray-100 shrink-0">
                    <div class="min-w-0">
                        <h2 class="font-semibold leading-snug text-[0.95rem]">${escapeHtml(product.name)}</h2>
                        <p class="text-xs text-gray-400 mt-0.5">${escapeHtml(product.category)}</p>
                    </div>
                </div>
                ${packs}
            </article>`;
    }

    function render() {
        const query = els.search.value.trim().toLowerCase();
        const sort = els.sort.value;

        let visible = PRODUCTS.filter(p =>
            (!activeCategory || p.category === activeCategory) &&
            (!query || p.searchText.includes(query))
        );

        const comparators = {
            category: (a, b) => a.category.localeCompare(b.category) || a.name.localeCompare(b.name),
            name: (a, b) => a.name.localeCompare(b.name),
            name_desc: (a, b) => b.name.localeCompare(a.name),
            price: (a, b) => a.minPrice - b.minPrice,
            price_desc: (a, b) => b.minPrice - a.minPrice,
        };
        visible.sort(comparators[sort] || comparators.category);

        els.results.innerHTML = visible.map(cardHtml).join('');
        els.empty.classList.toggle('hidden', visible.length > 0);
        els.count.textContent = visible.length === PRODUCTS.length
            ? `${PRODUCTS.length} products`
            : `${visible.length} of ${PRODUCTS.length} products`;
    }

    els.search.addEventListener('input', render);
    els.sort.addEventListener('change', render);
    els.categories.addEventListener('click', event => {
        const button = event.target.closest('.chip');
        if (!button) return;
        activeCategory = button.dataset.cat;
        els.categories.querySelectorAll('.chip').forEach(c => c.classList.toggle('active', c === button));
        render();
    });

    render();
</script>
@endif

</body>
</html>
