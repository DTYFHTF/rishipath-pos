<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>RishiPath — Pricing Intelligence Calculator</title>
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
    body { font-family: 'Inter', system-ui, sans-serif; background: #f8f7f4; }

    .tab-active { border-bottom: 3px solid #ea580c; color: #ea580c; font-weight: 600; }
    .tab-btn { transition: color 0.15s; }
    .tab-btn:not(.tab-active):hover { color: #374151; }
    .metric-card { transition: transform 0.15s, box-shadow 0.15s; }
    .metric-card:hover { transform: translateY(-1px); box-shadow: 0 4px 16px rgba(0,0,0,0.08); }
    .bar-fill { transition: width 0.4s ease; }
    .recommended-badge { background: #fef9c3; border: 1px solid #fde047; color: #713f12; font-size: 10px; font-weight: 600; padding: 1px 6px; border-radius: 99px; letter-spacing: 0.3px; }
    .gradient-header { background: linear-gradient(135deg, #ea580c 0%, #c2410c 100%); }
    .tier-bronze { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
    .tier-silver { background: #f1f5f9; color: #475569; border: 1px solid #94a3b8; }
    .tier-gold   { background: #fefce8; color: #713f12; border: 1px solid #fbbf24; }
    @media print { .no-print { display: none !important; } body { background: white !important; } .print-break { page-break-before: always; } }
    .num { font-variant-numeric: tabular-nums; }
    ::-webkit-scrollbar { width: 5px; height: 5px; }
    ::-webkit-scrollbar-track { background: #f1f1f1; }
    ::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 99px; }
    input:focus, select:focus { outline: none; }
    .product-pill { cursor: pointer; padding: 4px 12px; border-radius: 99px; font-size: 12px; border: 1.5px solid #e5e7eb; background: white; transition: all 0.15s; white-space: nowrap; }
    .product-pill:hover { border-color: #ea580c; color: #ea580c; }
    .product-pill.active-pill { background: #fff7ed; border-color: #ea580c; color: #ea580c; font-weight: 600; }
    .health-great { color: #16a34a; }
    .health-ok    { color: #d97706; }
    .health-warn  { color: #dc2626; }

    /* Product search dropdown */
    #productSearchResults {
      position: absolute; left: 0; right: 0; top: 100%; z-index: 50;
      background: white; border: 1px solid #e5e7eb; border-top: none;
      border-radius: 0 0 8px 8px; max-height: 280px; overflow-y: auto;
      box-shadow: 0 4px 16px rgba(0,0,0,0.10);
    }
    .search-item { padding: 8px 12px; cursor: pointer; transition: background 0.1s; }
    .search-item:hover { background: #fff7ed; }
    .search-item .si-name { font-weight: 600; font-size: 13px; color: #1f2937; }
    .search-item .si-variants { font-size: 11px; color: #6b7280; margin-top: 2px; }
    .variant-pill { display: inline-block; padding: 2px 8px; border-radius: 99px; font-size: 11px; border: 1px solid #e5e7eb; background: #f9fafb; cursor: pointer; transition: all 0.15s; margin: 2px; }
    .variant-pill:hover, .variant-pill.active-variant { background: #fff7ed; border-color: #ea580c; color: #ea580c; }
    .db-badge { display: inline-flex; align-items: center; gap: 4px; background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; border-radius: 6px; font-size: 10px; padding: 2px 6px; }

    /* MOQ tier table */
    .moq-row-highlight { background: #fff7ed; font-weight: 600; }
    .margin-badge { display: inline-block; padding: 2px 7px; border-radius: 99px; font-size: 11px; font-weight: 600; }
    .mb-great { background: #dcfce7; color: #166534; }
    .mb-ok    { background: #fef9c3; color: #713f12; }
    .mb-warn  { background: #fee2e2; color: #991b1b; }
  </style>
</head>
<body class="min-h-screen">

<!-- ============================================================ HEADER -->
<header class="gradient-header text-white shadow-md no-print sticky top-0 z-50">
  <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <span class="text-2xl">🌿</span>
      <div>
        <div class="font-bold text-lg leading-tight">RishiPath</div>
        <div class="text-orange-200 text-xs leading-tight">Pricing Intelligence Calculator</div>
      </div>
    </div>
    <div class="flex items-center gap-2">
      <select id="currency" onchange="updateAll()"
        class="bg-white/20 text-white border border-white/30 rounded-lg px-2 py-1.5 text-sm cursor-pointer">
        <option value="NPR">रू NPR — Nepal</option>
        <option value="INR">₹ INR — India</option>
        <option value="USD">$ USD</option>
      </select>
      <button onclick="window.print()"
        class="bg-white/20 hover:bg-white/30 text-white border border-white/30 rounded-lg px-3 py-1.5 text-sm transition flex items-center gap-1.5">
        🖨️ Print
      </button>
      @auth
      <a href="/admin" class="bg-white/20 hover:bg-white/30 text-white border border-white/30 rounded-lg px-3 py-1.5 text-sm transition">
        ← Admin
      </a>
      @endauth
    </div>
  </div>
</header>

<!-- ============================================================ MAIN -->
<div class="max-w-7xl mx-auto px-4 py-6 space-y-5">

  <!-- ─── PRODUCT SETUP PANEL ─── -->
  <div id="setup-panel" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
    <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
      <h2 class="font-semibold text-gray-800 text-base">Product Setup</h2>
      <div class="flex items-center gap-2 flex-wrap no-print">
        <div id="savedPillsRow" class="flex gap-2 flex-wrap"></div>
        <button onclick="saveCurrentProduct()"
          class="text-xs text-orange-600 border border-orange-300 rounded-full px-3 py-1 hover:bg-orange-50 transition">
          + Save Preset
        </button>
        <button onclick="resetMarkups()"
          class="text-xs text-gray-400 hover:text-gray-600 transition ml-1">
          Reset defaults
        </button>
      </div>
    </div>

    <!-- Row 1: Product Search + Variant Selector + Cost Price -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 mb-4">

      <!-- Product Search -->
      <div class="relative">
        <label class="block text-xs font-medium text-gray-500 mb-1 flex items-center gap-1">
          Product
          <span id="dbBadge" class="db-badge hidden">🔗 Connected to POS</span>
        </label>
        <input type="text" id="productSearch" placeholder="Search product name or SKU…"
          autocomplete="off"
          class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-orange-300"
          oninput="onProductSearch()" onfocus="onProductSearch()" />
        <div id="productSearchResults" class="hidden"></div>
      </div>

      <!-- Variant Selector (appears after product selected) -->
      <div id="variantPickerWrap" class="hidden">
        <label class="block text-xs font-medium text-gray-500 mb-1">Pack Size / Variant</label>
        <div id="variantPills" class="flex flex-wrap gap-1 mt-1 min-h-[34px]"></div>
      </div>

      <!-- Cost Price -->
      <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">
          Cost Price (CP) <span class="text-gray-400 font-normal">— what it costs you</span>
        </label>
        <div class="relative">
          <span id="sym-main" class="absolute left-3 top-2.5 text-gray-400 text-sm">रू</span>
          <input type="number" id="costPrice" placeholder="0.00" min="0" step="0.01"
            class="w-full pl-8 pr-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-orange-300 num"
            oninput="updateAll()" />
        </div>
        <div id="cpSource" class="text-xs text-gray-400 mt-0.5 hidden"></div>
      </div>
    </div>

    <!-- Current product info line -->
    <div id="selectedProductInfo" class="hidden bg-orange-50 border border-orange-100 rounded-lg px-3 py-2 mb-4 text-xs text-orange-700 flex items-center justify-between">
      <span id="selectedProductInfoText"></span>
      <button onclick="clearProductSelection()" class="text-orange-400 hover:text-orange-600 ml-2">✕</button>
    </div>

    <!-- Row 2: Markup Config -->
    <div class="border-t border-gray-100 pt-4">
      <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-3">Pricing Formula Configuration</p>
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-blue-50 rounded-xl p-3">
          <label class="block text-xs text-blue-600 font-medium mb-1">Standard Wholesale</label>
          <div class="flex items-center gap-1">
            <span class="text-gray-400 text-xs">CP +</span>
            <input type="number" id="wholesaleMarkup" value="13" min="0" max="999" step="0.5"
              class="w-16 px-2 py-1 border border-blue-200 rounded text-sm text-center focus:ring-2 focus:ring-blue-300"
              oninput="updateAll()" />
            <span class="text-gray-400 text-xs">%</span>
          </div>
          <p class="text-xs text-blue-400 mt-1">Distributors / agents</p>
        </div>
        <div class="bg-green-50 rounded-xl p-3">
          <label class="block text-xs text-green-600 font-medium mb-1">Visited Store</label>
          <div class="flex items-center gap-1">
            <span class="text-gray-400 text-xs">CP +</span>
            <input type="number" id="visitedMarkup" value="15" min="0" max="999" step="0.5"
              class="w-16 px-2 py-1 border border-green-200 rounded text-sm text-center focus:ring-2 focus:ring-green-300"
              oninput="updateAll()" />
            <span class="text-gray-400 text-xs">%</span>
          </div>
          <p class="text-xs text-green-400 mt-1">Team visits store</p>
        </div>
        <div class="bg-orange-50 rounded-xl p-3">
          <label class="block text-xs text-orange-600 font-medium mb-1">MRP / Retail</label>
          <div class="flex items-center gap-1">
            <span class="text-gray-400 text-xs">CP +</span>
            <input type="number" id="mrpMarkup" value="25" min="0" max="999" step="0.5"
              class="w-16 px-2 py-1 border border-orange-200 rounded text-sm text-center focus:ring-2 focus:ring-orange-300"
              oninput="updateAll()" />
            <span class="text-gray-400 text-xs">%</span>
          </div>
          <p class="text-xs text-orange-400 mt-1">End consumer / shelf</p>
        </div>
        <div class="bg-purple-50 rounded-xl p-3">
          <label class="block text-xs text-purple-600 font-medium mb-1">Partner Profit Share</label>
          <div class="flex items-center gap-1">
            <input type="number" id="partnerShare" value="30" min="0" max="100" step="1"
              class="w-16 px-2 py-1 border border-purple-200 rounded text-sm text-center focus:ring-2 focus:ring-purple-300"
              oninput="updateAll()" />
            <span class="text-gray-400 text-xs">% of profit</span>
          </div>
          <p class="text-xs text-purple-400 mt-1">% of margin to partner</p>
        </div>
      </div>
    </div>
  </div>

  <!-- ─── QUICK SUMMARY BAR ─── -->
  <div id="summary-bar" class="grid grid-cols-2 sm:grid-cols-4 gap-3"></div>

  <!-- ─── TABS ─── -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="flex border-b border-gray-100 overflow-x-auto no-print bg-gray-50">
      <button onclick="switchTab('matrix')"  id="tab-matrix"  class="tab-btn tab-active px-4 py-3.5 text-sm whitespace-nowrap">📊 Price Matrix</button>
      <button onclick="switchTab('moq')"     id="tab-moq"     class="tab-btn px-4 py-3.5 text-sm text-gray-500 whitespace-nowrap">📏 MOQ Pricing</button>
      <button onclick="switchTab('chain')"   id="tab-chain"   class="tab-btn px-4 py-3.5 text-sm text-gray-500 whitespace-nowrap">⛓️ Chain Analysis</button>
      <button onclick="switchTab('partner')" id="tab-partner" class="tab-btn px-4 py-3.5 text-sm text-gray-500 whitespace-nowrap">🤝 Partner Pitch</button>
      <button onclick="switchTab('volume')"  id="tab-volume"  class="tab-btn px-4 py-3.5 text-sm text-gray-500 whitespace-nowrap">📦 Volume Calc</button>
      <button onclick="switchTab('smart')"   id="tab-smart"   class="tab-btn px-4 py-3.5 text-sm text-gray-500 whitespace-nowrap">🎯 Smart Tools</button>
    </div>

    <!-- ──── TAB 1: PRICE MATRIX ──── -->
    <div id="content-matrix" class="tab-content p-5">
      <p class="text-xs text-gray-400 mb-5 italic">Shows all pricing tiers for a single unit. Enter Cost Price above to populate.</p>
      <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4 mb-6" id="tier-cards"></div>
      <div class="bg-gray-50 rounded-xl p-4 mb-6" id="profit-bar-visual"></div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm" id="matrix-table">
          <thead>
            <tr class="bg-gray-50 text-gray-400 text-xs uppercase tracking-wide">
              <th class="text-left px-4 py-3 rounded-tl-lg font-medium">Channel / Tier</th>
              <th class="text-right px-4 py-3 font-medium">Selling Price</th>
              <th class="text-right px-4 py-3 font-medium">Markup %</th>
              <th class="text-right px-4 py-3 font-medium">Your Profit / Unit</th>
              <th class="text-right px-4 py-3 font-medium">Gross Margin %</th>
              <th class="text-right px-4 py-3 rounded-tr-lg font-medium">Best For</th>
            </tr>
          </thead>
          <tbody id="matrix-tbody">
            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-300">Enter cost price to see all pricing tiers</td></tr>
          </tbody>
        </table>
      </div>
      <div class="flex gap-4 mt-3 text-xs text-gray-400 no-print">
        <span><span class="health-great font-bold">●</span> ≥ 20% — Healthy</span>
        <span><span class="health-ok font-bold">●</span> 10–19% — Acceptable</span>
        <span><span class="health-warn font-bold">●</span> &lt; 10% — Thin margin</span>
      </div>
    </div>

    <!-- ──── TAB 2: MOQ PRICING (NEW) ──── -->
    <div id="content-moq" class="tab-content hidden p-5">
      <div class="flex flex-wrap items-start justify-between gap-3 mb-5">
        <div>
          <h3 class="font-semibold text-gray-800 text-sm mb-1">📏 MOQ & Pack-Size Tiered Pricing</h3>
          <p class="text-xs text-gray-400">
            Spices are sold from 1g to 50kg+. Smaller packs command higher per-gram margins because of packaging cost,
            labour, and premium convenience. Use this tab to plan prices across your full pack-size range.
          </p>
        </div>
        <div class="no-print">
          <button onclick="addMoqRow()" class="text-xs bg-orange-50 text-orange-600 border border-orange-200 rounded-lg px-3 py-1.5 hover:bg-orange-100 transition">+ Add Row</button>
          <button onclick="resetMoqRows()" class="text-xs text-gray-400 hover:text-gray-600 ml-2 transition">Reset</button>
        </div>
      </div>

      <!-- Cost basis banner -->
      <div id="moqCostBanner" class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-2 mb-4 text-sm text-amber-700 hidden">
        <strong>Base cost (from selected variant):</strong> <span id="moqBaseCost">—</span> per unit.
        You can override the per-pack cost in each row below.
      </div>

      <!-- Margin guide callout -->
      <div class="bg-orange-50 border border-orange-100 rounded-xl p-4 mb-5 text-xs text-gray-600">
        <div class="font-semibold text-orange-700 mb-2">💡 Why does a 1g pack need a much higher margin?</div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <div class="bg-white rounded-lg p-2 text-center border border-orange-100">
            <div class="text-lg font-bold text-orange-600">50–80%</div>
            <div class="text-gray-500">1g – 10g packs</div>
            <div class="text-gray-400 text-[10px]">Mostly packaging cost</div>
          </div>
          <div class="bg-white rounded-lg p-2 text-center border border-orange-100">
            <div class="text-lg font-bold text-green-600">30–50%</div>
            <div class="text-gray-500">25g – 100g packs</div>
            <div class="text-gray-400 text-[10px]">Premium retail segment</div>
          </div>
          <div class="bg-white rounded-lg p-2 text-center border border-orange-100">
            <div class="text-lg font-bold text-blue-600">15–30%</div>
            <div class="text-gray-500">250g – 1kg packs</div>
            <div class="text-gray-400 text-[10px]">Standard consumer</div>
          </div>
          <div class="bg-white rounded-lg p-2 text-center border border-orange-100">
            <div class="text-lg font-bold text-gray-600">8–20%</div>
            <div class="text-gray-500">5kg – 50kg bulk</div>
            <div class="text-gray-400 text-[10px]">B2B / volume buyers</div>
          </div>
        </div>
      </div>

      <!-- MOQ Table -->
      <div class="overflow-x-auto">
        <table class="w-full text-sm" id="moq-table">
          <thead>
            <tr class="bg-gray-50 text-gray-400 text-xs uppercase tracking-wide">
              <th class="text-left px-3 py-2 font-medium w-28">Pack Size</th>
              <th class="text-left px-3 py-2 font-medium w-20">Unit</th>
              <th class="text-right px-3 py-2 font-medium w-32">Cost / Pack <span class="text-gray-300 normal-case font-normal">(edit)</span></th>
              <th class="text-right px-3 py-2 font-medium w-32">Packaging Cost</th>
              <th class="text-right px-3 py-2 font-medium w-32">Total Cost</th>
              <th class="text-right px-3 py-2 font-medium w-32">Suggested Price</th>
              <th class="text-right px-3 py-2 font-medium w-28">Target Margin %</th>
              <th class="text-right px-3 py-2 font-medium w-32">Your Price <span class="text-gray-300 normal-case font-normal">(edit)</span></th>
              <th class="text-right px-3 py-2 font-medium w-28">Actual Margin</th>
              <th class="text-right px-3 py-2 font-medium w-28">Profit / Pack</th>
              <th class="px-3 py-2 w-10 no-print"></th>
            </tr>
          </thead>
          <tbody id="moq-tbody">
            <!-- Populated by JS -->
          </tbody>
        </table>
      </div>

      <!-- MOQ Summary -->
      <div id="moqSummary" class="mt-5 grid grid-cols-2 sm:grid-cols-4 gap-3 hidden">
        <!-- Populated by JS -->
      </div>

      <!-- MOQ Price-per-gram chart -->
      <div class="mt-6 bg-gray-50 rounded-xl p-4">
        <h4 class="font-semibold text-gray-700 text-sm mb-3">Price per gram across pack sizes</h4>
        <canvas id="moqChart" height="180"></canvas>
        <p class="text-xs text-gray-400 mt-2 text-center">Smaller packs always have higher price/gram — this is expected and correct.</p>
      </div>
    </div>

    <!-- ──── TAB 3: CHAIN ANALYSIS ──── -->
    <div id="content-chain" class="tab-content hidden p-5">
      <p class="text-xs text-gray-400 mb-5 italic">Understand the full profit chain — who earns what between you, distributors, and end customers.</p>
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-gray-50 rounded-xl p-4">
          <h3 class="font-semibold text-gray-700 text-sm mb-3">Stacked Profit Distribution per Unit</h3>
          <canvas id="chainChart" height="260"></canvas>
          <p class="text-xs text-gray-400 mt-2 text-center">Gray = your cost. Colored = your profit. Light = partner/retailer margin</p>
        </div>
        <div>
          <h3 class="font-semibold text-gray-700 text-sm mb-3">Value Chain per Channel</h3>
          <div id="value-chain-cards" class="space-y-3"><p class="text-gray-300 text-sm">Enter cost price to see value chain</p></div>
        </div>
      </div>
      <h3 class="font-semibold text-gray-700 text-sm mb-3">Channel-by-Channel Profit Capture</h3>
      <div id="channel-roi-table" class="overflow-x-auto"><p class="text-gray-300 text-sm">Enter cost price above</p></div>
      <div id="strategy-insight" class="mt-5 bg-amber-50 border border-amber-200 rounded-xl p-4 hidden">
        <h4 class="font-semibold text-amber-800 text-sm mb-2">💡 Channel Strategy Insight</h4>
        <div id="strategy-text" class="text-sm text-amber-700 space-y-1"></div>
      </div>
    </div>

    <!-- ──── TAB 4: PARTNER PITCH ──── -->
    <div id="content-partner" class="tab-content hidden p-5">
      <div class="gradient-header text-white rounded-xl p-4 mb-5">
        <h3 class="font-bold text-base mb-0.5">🤝 Retail Partner Earning Calculator</h3>
        <p class="text-orange-100 text-xs">Show this to retail partners during sales conversations to demonstrate their earning potential.</p>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 mb-5 no-print">
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1">You sell to partner at</label>
          <select id="partnerBuyMode" onchange="updatePartnerTab()" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
            <option value="ws">Standard Wholesale (CP +13%)</option>
            <option value="vs" selected>Visited Store Price (CP +15%)</option>
            <option value="ps">Revenue Share Price</option>
            <option value="custom">Custom Price</option>
          </select>
        </div>
        <div id="custom-buy-div" class="hidden">
          <label class="block text-xs font-medium text-gray-500 mb-1">Your custom price to partner</label>
          <div class="relative">
            <span class="sym-tag absolute left-3 top-2.5 text-gray-400 text-sm">रू</span>
            <input type="number" id="customPartnerBuy" min="0" step="0.01" placeholder="0.00" class="w-full pl-8 pr-3 py-2 border border-gray-200 rounded-lg text-sm num" oninput="updatePartnerTab()" />
          </div>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1">Partner sells at</label>
          <select id="partnerSellMode" onchange="updatePartnerTab()" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
            <option value="mrp" selected>MRP (Recommended Retail)</option>
            <option value="custom">Custom Selling Price</option>
          </select>
        </div>
        <div id="custom-sell-div" class="hidden">
          <label class="block text-xs font-medium text-gray-500 mb-1">Partner's custom sell price</label>
          <div class="relative">
            <span class="sym-tag absolute left-3 top-2.5 text-gray-400 text-sm">रू</span>
            <input type="number" id="customPartnerSell" min="0" step="0.01" placeholder="0.00" class="w-full pl-8 pr-3 py-2 border border-gray-200 rounded-lg text-sm num" oninput="updatePartnerTab()" />
          </div>
        </div>
      </div>
      <div id="partner-metrics" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5"></div>
      <div class="bg-purple-50 rounded-xl p-5 border border-purple-100 mb-5">
        <h4 class="font-semibold text-purple-800 text-sm mb-4">Monthly Revenue Projection for Partner</h4>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="block text-xs text-gray-500 mb-1">Estimated Daily Sales (units)</label>
            <input type="number" id="dailyUnits" value="5" min="1" class="w-full px-3 py-2 border border-purple-200 rounded-lg text-sm num" oninput="updatePartnerTab()" />
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">Operating Days / Month</label>
            <input type="number" id="opDays" value="26" min="1" max="31" class="w-full px-3 py-2 border border-purple-200 rounded-lg text-sm num" oninput="updatePartnerTab()" />
          </div>
          <div class="flex flex-col justify-center items-center bg-white rounded-xl p-3 border border-purple-200">
            <div class="text-xs text-purple-400 mb-0.5">Partner Earns Monthly</div>
            <div class="text-2xl font-bold text-purple-700 num" id="monthly-partner-profit">रू 0</div>
            <div class="text-xs text-purple-400" id="monthly-formula">0 units × 26 days × रू0</div>
          </div>
        </div>
        <div class="mt-4 overflow-x-auto">
          <table class="w-full text-sm">
            <thead><tr class="text-xs text-purple-400 uppercase"><th class="text-left py-1 font-medium">Daily</th><th class="text-right py-1 font-medium">Monthly Units</th><th class="text-right py-1 font-medium">Monthly Revenue</th><th class="text-right py-1 font-medium">Partner Profit</th><th class="text-right py-1 font-medium">Our Revenue</th></tr></thead>
            <tbody id="projection-tbody"></tbody>
          </table>
        </div>
      </div>
      <div class="border-2 border-dashed border-orange-300 rounded-2xl p-6 bg-gradient-to-br from-orange-50 to-amber-50">
        <div class="text-center mb-4">
          <div class="text-3xl mb-1">🌿</div>
          <div class="text-xl font-bold text-orange-700">RishiPath Partner Program</div>
          <div class="text-gray-500 text-sm">Retail Partnership Opportunity</div>
          <div id="pitch-product-name" class="mt-1 text-sm font-semibold text-gray-700"></div>
        </div>
        <div id="partner-card-content" class="text-center text-gray-400 text-sm py-4">Enter product cost price above to generate the partner pitch card</div>
      </div>
    </div>

    <!-- ──── TAB 5: VOLUME CALCULATOR ──── -->
    <div id="content-volume" class="tab-content hidden p-5">
      <p class="text-xs text-gray-400 mb-5 italic">Calculate revenue and profit for any order quantity at any pricing tier.</p>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1">Pricing Channel</label>
          <select id="volChannel" onchange="updateVolumeTab()" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
            <option value="ws">Standard Wholesale</option>
            <option value="vs">Visited Store Price</option>
            <option value="ps">Partner Share Price</option>
            <option value="mrp">MRP / Retail Price</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1">Number of Units</label>
          <input type="number" id="volUnits" value="100" min="1" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm num" oninput="updateVolumeTab()" />
        </div>
        <div class="flex flex-col justify-end">
          <div id="vol-unit-economics" class="text-sm text-gray-500">— enter cost price above —</div>
        </div>
      </div>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="metric-card bg-blue-50 rounded-xl p-4 border border-blue-100 text-center"><div class="text-xs text-blue-400 font-medium uppercase mb-1">Total Revenue</div><div class="text-2xl font-bold text-blue-700 num" id="vol-revenue">—</div><div class="text-xs text-blue-400 num" id="vol-price-tag"></div></div>
        <div class="metric-card bg-green-50 rounded-xl p-4 border border-green-100 text-center"><div class="text-xs text-green-400 font-medium uppercase mb-1">Your Total Profit</div><div class="text-2xl font-bold text-green-700 num" id="vol-profit">—</div><div class="text-xs text-green-400 num" id="vol-ppu"></div></div>
        <div class="metric-card bg-gray-50 rounded-xl p-4 border border-gray-100 text-center"><div class="text-xs text-gray-400 font-medium uppercase mb-1">Total Cost Outlay</div><div class="text-2xl font-bold text-gray-700 num" id="vol-cost">—</div><div class="text-xs text-gray-400 num" id="vol-cp-tag"></div></div>
        <div class="metric-card bg-orange-50 rounded-xl p-4 border border-orange-100 text-center"><div class="text-xs text-orange-400 font-medium uppercase mb-1">Gross Margin</div><div class="text-2xl font-bold text-orange-700 num" id="vol-margin">—</div><div class="text-xs text-orange-400">On selling price</div></div>
      </div>
      <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-5">
        <h4 class="font-semibold text-amber-800 text-sm mb-3">💰 Max Bulk Discount You Can Offer</h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs text-gray-500 mb-1">Minimum margin to maintain (%)</label>
            <input type="number" id="minMargin" value="10" min="0" max="100" step="0.5" class="w-full px-3 py-2 border border-amber-200 rounded-lg text-sm" oninput="updateVolumeTab()" />
          </div>
          <div id="bulk-discount-result" class="flex items-center"><p class="text-xs text-amber-700">Max discount: <span id="max-discount-pct" class="font-bold text-amber-900">—</span></p></div>
        </div>
      </div>
      <h3 class="font-semibold text-gray-700 text-sm mb-3">Order Size Comparison</h3>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead><tr class="bg-gray-50 text-gray-400 text-xs uppercase tracking-wide"><th class="text-left px-4 py-3 font-medium">Units</th><th class="text-right px-4 py-3 font-medium">WS Revenue</th><th class="text-right px-4 py-3 font-medium">Visited Revenue</th><th class="text-right px-4 py-3 font-medium">MRP Revenue</th><th class="text-right px-4 py-3 font-medium">WS Profit</th><th class="text-right px-4 py-3 font-medium">Visited Profit</th><th class="text-right px-4 py-3 font-medium">MRP Profit</th></tr></thead>
          <tbody id="vol-comparison-tbody"><tr><td colspan="7" class="px-4 py-8 text-center text-gray-300">Enter cost price above</td></tr></tbody>
        </table>
      </div>
    </div>

    <!-- ──── TAB 6: SMART TOOLS ──── -->
    <div id="content-smart" class="tab-content hidden p-5">
      <p class="text-xs text-gray-400 mb-5 italic">Advanced pricing tools for strategic decisions.</p>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <!-- Target Margin -->
        <div class="bg-green-50 rounded-xl p-5 border border-green-200">
          <h3 class="font-semibold text-green-800 text-sm mb-4">🎯 Target Margin → Required Price</h3>
          <p class="text-xs text-gray-500 mb-3">What price must you charge to achieve a specific gross margin?</p>
          <div class="space-y-3">
            <div><label class="block text-xs text-gray-500 mb-1">Your Cost Price</label><div class="font-semibold text-gray-700 text-sm" id="sm-cp-show">Enter CP above ↑</div></div>
            <div><label class="block text-xs text-gray-500 mb-1">Desired Gross Margin (%)</label><input type="number" id="targetMarginPct" value="20" min="1" max="99" step="0.5" class="w-full px-3 py-2 border border-green-300 rounded-lg text-sm" oninput="updateSmartTab()" /></div>
            <div class="bg-white rounded-lg p-3 border border-green-100">
              <div class="text-xs text-gray-400 mb-0.5">Required Selling Price:</div>
              <div class="text-2xl font-bold text-green-700 num" id="sm-target-price">—</div>
              <div class="flex justify-between text-xs text-gray-400 mt-1"><span>Profit/unit: <span class="text-green-600 font-medium num" id="sm-target-profit">—</span></span><span>Markup: <span class="font-medium" id="sm-target-markup">—</span></span></div>
            </div>
            <p class="text-xs text-gray-400">Formula: Price = CP ÷ (1 − margin%)</p>
          </div>
        </div>
        <!-- Competitor -->
        <div class="bg-red-50 rounded-xl p-5 border border-red-200">
          <h3 class="font-semibold text-red-800 text-sm mb-4">⚔️ Competitor Price Analysis</h3>
          <p class="text-xs text-gray-500 mb-3">How does your pricing compare?</p>
          <div class="space-y-3">
            <div><label class="block text-xs text-gray-500 mb-1">Competitor's Selling Price</label><div class="relative"><span class="sym-tag absolute left-3 top-2.5 text-gray-400 text-sm">रू</span><input type="number" id="competitorPrice" placeholder="0.00" min="0" class="w-full pl-8 pr-3 py-2 border border-red-300 rounded-lg text-sm num" oninput="updateSmartTab()" /></div></div>
            <div id="comp-analysis" class="bg-white rounded-lg p-3 border border-red-100 text-xs text-gray-400">Enter competitor's price to analyze positioning</div>
          </div>
        </div>
        <!-- CP Impact -->
        <div class="bg-blue-50 rounded-xl p-5 border border-blue-200">
          <h3 class="font-semibold text-blue-800 text-sm mb-4">📈 Raw Material Cost Change Impact</h3>
          <p class="text-xs text-gray-500 mb-3">How must selling prices change to maintain margins when CP changes?</p>
          <div class="space-y-3">
            <div><label class="block text-xs text-gray-500 mb-1">CP change (%)</label><div class="flex gap-2"><select id="cpChangeDir" onchange="updateSmartTab()" class="px-3 py-2 border border-blue-200 rounded-lg text-sm"><option value="up">↑ Increase</option><option value="down">↓ Decrease</option></select><input type="number" id="cpChangePct" value="10" min="0" max="200" step="1" class="flex-1 px-3 py-2 border border-blue-200 rounded-lg text-sm num" oninput="updateSmartTab()" /><span class="flex items-center text-sm text-gray-400">%</span></div></div>
            <div id="cp-impact-table" class="bg-white rounded-lg p-3 border border-blue-100 text-xs text-gray-400">Enter cost price above</div>
          </div>
        </div>
        <!-- Tax -->
        <div class="bg-yellow-50 rounded-xl p-5 border border-yellow-200">
          <h3 class="font-semibold text-yellow-800 text-sm mb-4">🧾 Tax-Inclusive Pricing</h3>
          <p class="text-xs text-gray-500 mb-3">All prices above are pre-tax. See tax-inclusive values here.</p>
          <div class="space-y-3">
            <div><label class="block text-xs text-gray-500 mb-1">Tax Rate</label><div class="flex gap-2"><select id="taxPreset" onchange="applyTaxPreset()" class="flex-1 px-3 py-2 border border-yellow-300 rounded-lg text-sm"><option value="0">No Tax</option><option value="13" selected>VAT 13% (Nepal)</option><option value="5">GST 5%</option><option value="12">GST 12%</option><option value="18">GST 18%</option><option value="custom">Custom</option></select><input type="number" id="taxRate" value="13" min="0" max="100" step="0.1" class="w-20 px-3 py-2 border border-yellow-300 rounded-lg text-sm text-center num" oninput="updateSmartTab()" /><span class="flex items-center text-sm text-gray-400">%</span></div></div>
            <div id="tax-price-table" class="bg-white rounded-lg p-3 border border-yellow-100 text-xs text-gray-400">Enter cost price above</div>
          </div>
        </div>
        <!-- Break-Even -->
        <div class="bg-indigo-50 rounded-xl p-5 border border-indigo-200 md:col-span-2">
          <h3 class="font-semibold text-indigo-800 text-sm mb-4">📊 Break-Even Analysis</h3>
          <p class="text-xs text-gray-500 mb-3">How many units to sell to cover fixed costs?</p>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div><label class="block text-xs text-gray-500 mb-1">Fixed Monthly Costs</label><div class="relative"><span class="sym-tag absolute left-3 top-2.5 text-gray-400 text-sm">रू</span><input type="number" id="fixedCosts" placeholder="50000" min="0" class="w-full pl-8 pr-3 py-2 border border-indigo-200 rounded-lg text-sm num" oninput="updateSmartTab()" /></div></div>
            <div><label class="block text-xs text-gray-500 mb-1">Channel for Break-Even</label><select id="beChannel" onchange="updateSmartTab()" class="w-full px-3 py-2 border border-indigo-200 rounded-lg text-sm"><option value="ws">Standard Wholesale</option><option value="vs">Visited Store</option><option value="mrp">MRP / Retail</option></select></div>
            <div id="be-result" class="flex flex-col justify-center items-center bg-white rounded-xl p-3 border border-indigo-200 text-center"><div class="text-xs text-indigo-400 mb-0.5">Break-Even Units / Month</div><div class="text-2xl font-bold text-indigo-700 num" id="be-units">—</div><div class="text-xs text-indigo-400 num" id="be-revenue">Enter fixed costs above</div></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<footer class="text-center text-xs text-gray-400 py-6 no-print">
  RishiPath Pricing Calculator — Calculations are for planning purposes only. Actual prices may vary.
</footer>

<script>
// ════════════════════════════════════════════════════════
//  STATE
// ════════════════════════════════════════════════════════
let chainChartInstance = null;
let moqChartInstance   = null;
const SYM = { NPR: 'रू', INR: '₹', USD: '$' };
const savedProducts = JSON.parse(localStorage.getItem('rp_products') || '[]');
let selectedProduct = null;     // { id, name, sku, category }
let selectedVariant = null;     // { id, pack_size, unit, cost_price, base_price, mrp_india, sku }
let searchTimeout   = null;

// Default MOQ rows — common spice pack sizes
const DEFAULT_MOQ_ROWS = [
  { packSize: 1,    unit: 'g',   packCost: 2,    targetMargin: 70, yourPrice: null },
  { packSize: 5,    unit: 'g',   packCost: 1.5,  targetMargin: 65, yourPrice: null },
  { packSize: 10,   unit: 'g',   packCost: 1.2,  targetMargin: 60, yourPrice: null },
  { packSize: 25,   unit: 'g',   packCost: 1,    targetMargin: 50, yourPrice: null },
  { packSize: 50,   unit: 'g',   packCost: 0.8,  targetMargin: 40, yourPrice: null },
  { packSize: 100,  unit: 'g',   packCost: 0.5,  targetMargin: 35, yourPrice: null },
  { packSize: 250,  unit: 'g',   packCost: 0.3,  targetMargin: 25, yourPrice: null },
  { packSize: 500,  unit: 'g',   packCost: 0.2,  targetMargin: 20, yourPrice: null },
  { packSize: 1,    unit: 'kg',  packCost: 0.1,  targetMargin: 15, yourPrice: null },
  { packSize: 5,    unit: 'kg',  packCost: 0.05, targetMargin: 12, yourPrice: null },
  { packSize: 25,   unit: 'kg',  packCost: 0,    targetMargin: 10, yourPrice: null },
];
let moqRows = JSON.parse(JSON.stringify(DEFAULT_MOQ_ROWS));

// ════════════════════════════════════════════════════════
//  HELPERS
// ════════════════════════════════════════════════════════
function sym() { return SYM[document.getElementById('currency').value] || 'रू'; }

function fmt(v, decimals = 2) {
  if (isNaN(v) || v == null) return '—';
  const abs = Math.abs(v);
  const formatted = abs.toLocaleString('en-IN', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
  return sym() + ' ' + (v < 0 ? '-' : '') + formatted;
}

function fmtN(v, d = 2) { return isNaN(v) ? '0' : parseFloat(v).toFixed(d); }
function pct(v) { return fmtN(v) + '%'; }
function cp() { return parseFloat(document.getElementById('costPrice').value) || 0; }

function markups() {
  return {
    ws:  parseFloat(document.getElementById('wholesaleMarkup').value) / 100 || 0.13,
    vs:  parseFloat(document.getElementById('visitedMarkup').value)   / 100 || 0.15,
    mrp: parseFloat(document.getElementById('mrpMarkup').value)       / 100 || 0.25,
    ps:  parseFloat(document.getElementById('partnerShare').value)    / 100 || 0.30,
  };
}

function compute() {
  const C = cp();
  const m = markups();
  const ws  = C * (1 + m.ws);
  const vs  = C * (1 + m.vs);
  const mrp = C * (1 + m.mrp);
  const totalProfit  = mrp - C;
  const partnerEarns = m.ps * totalProfit;
  const ps           = mrp - partnerEarns;
  const psOurProfit  = ps - C;
  return { C, ws, vs, mrp, ps, totalProfit, partnerEarns, psOurProfit, m };
}

function grossMargin(price, cost) { return price <= 0 ? 0 : (price - cost) / price * 100; }
function markupPct(price, cost) { return cost <= 0 ? 0 : (price - cost) / cost * 100; }
function marginClass(p) { return p >= 20 ? 'health-great' : p >= 10 ? 'health-ok' : 'health-warn'; }
function marginBg(p) { return p >= 20 ? 'bg-green-600' : p >= 10 ? 'bg-yellow-500' : 'bg-red-500'; }
function marginBadge(p) { const cls = p >= 20 ? 'mb-great' : p >= 10 ? 'mb-ok' : 'mb-warn'; return `<span class="margin-badge ${cls}">${fmtN(p)}%</span>`; }

// Unit normalization to grams for cross-pack comparison
const UNIT_TO_GRAMS = { 'g': 1, 'gm': 1, 'gms': 1, 'gram': 1, 'grams': 1, 'kg': 1000, 'ml': 1, 'l': 1000, 'ltr': 1000, 'pcs': 1, 'piece': 1, 'pieces': 1, 'nos': 1 };
function toBaseUnit(size, unit) {
  const factor = UNIT_TO_GRAMS[(unit || 'g').toLowerCase()] || 1;
  return size * factor;
}

// ════════════════════════════════════════════════════════
//  PRODUCT SEARCH (API)
// ════════════════════════════════════════════════════════
function onProductSearch() {
  clearTimeout(searchTimeout);
  const q = document.getElementById('productSearch').value.trim();
  if (q.length < 2) { hideSearchResults(); return; }
  searchTimeout = setTimeout(() => fetchProducts(q), 250);
}

async function fetchProducts(q) {
  try {
    const res = await fetch(`/api/price-calculator/products?q=${encodeURIComponent(q)}`, {
      headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' }
    });
    if (!res.ok) { hideSearchResults(); return; }
    const data = await res.json();
    renderSearchResults(data.products || []);
    document.getElementById('dbBadge').classList.remove('hidden');
  } catch(e) {
    hideSearchResults();
  }
}

function renderSearchResults(products) {
  const el = document.getElementById('productSearchResults');
  if (!products.length) {
    el.innerHTML = '<div class="search-item text-gray-400 text-xs">No products found</div>';
    el.classList.remove('hidden');
    return;
  }
  el.innerHTML = products.map(p => `
    <div class="search-item" onclick="selectProduct(${JSON.stringify(p).replace(/"/g, '&quot;')})">
      <div class="si-name">${p.name}${p.name_hindi ? ' <span class="text-gray-400 font-normal text-xs">— ' + p.name_hindi + '</span>' : ''}</div>
      <div class="si-variants">${p.sku ? 'SKU: ' + p.sku + ' · ' : ''}${p.variants.length} variant${p.variants.length !== 1 ? 's' : ''}: ${p.variants.map(v => v.pack_size + v.unit).join(', ')}</div>
    </div>
  `).join('');
  el.classList.remove('hidden');
}

function hideSearchResults() {
  document.getElementById('productSearchResults').classList.add('hidden');
}

function selectProduct(p) {
  selectedProduct = p;
  document.getElementById('productSearch').value = p.name;
  hideSearchResults();
  renderVariantPills(p.variants);
  document.getElementById('variantPickerWrap').classList.remove('hidden');
  // If only one variant, auto-select it
  if (p.variants.length === 1) selectVariant(p.variants[0]);
  else document.getElementById('selectedProductInfo').classList.add('hidden');
}

function renderVariantPills(variants) {
  document.getElementById('variantPills').innerHTML = variants.map(v => `
    <span class="variant-pill ${selectedVariant && selectedVariant.id === v.id ? 'active-variant' : ''}"
          onclick='selectVariant(${JSON.stringify(v).replace(/'/g, "&#39;")})'>
      ${v.pack_size}${v.unit}${v.sku ? ' <span class="text-gray-400 text-[10px]">·' + v.sku + '</span>' : ''}
    </span>
  `).join('');
}

function selectVariant(v) {
  selectedVariant = v;
  // Fill cost price
  if (v.cost_price && v.cost_price > 0) {
    document.getElementById('costPrice').value = v.cost_price;
    document.getElementById('cpSource').textContent = `Auto-filled from ${v.pack_size}${v.unit} variant (SKU: ${v.sku || 'N/A'})`;
    document.getElementById('cpSource').classList.remove('hidden');
  }
  // Show info bar
  const infoText = [
    selectedProduct ? selectedProduct.name : '',
    `${v.pack_size}${v.unit}`,
    v.cost_price ? `CP: ${fmt(v.cost_price)}` : '',
    v.base_price ? `Base: ${fmt(v.base_price)}` : '',
    v.mrp_india  ? `MRP: ${fmt(v.mrp_india)}` : '',
  ].filter(Boolean).join(' · ');
  document.getElementById('selectedProductInfoText').textContent = infoText;
  document.getElementById('selectedProductInfo').classList.remove('hidden');

  // If variant has MRP, pre-fill MRP markup accordingly
  if (v.mrp_india && v.cost_price && v.cost_price > 0) {
    const impliedMrpMarkup = ((v.mrp_india - v.cost_price) / v.cost_price * 100);
    document.getElementById('mrpMarkup').value = fmtN(impliedMrpMarkup);
  }

  // Re-render variant pills to highlight selection
  if (selectedProduct) renderVariantPills(selectedProduct.variants);

  // Update MOQ cost banner
  updateMoqCostBanner(v);
  updateAll();
}

function clearProductSelection() {
  selectedProduct = null;
  selectedVariant = null;
  document.getElementById('productSearch').value = '';
  document.getElementById('variantPickerWrap').classList.add('hidden');
  document.getElementById('selectedProductInfo').classList.add('hidden');
  document.getElementById('cpSource').classList.add('hidden');
  document.getElementById('moqCostBanner').classList.add('hidden');
}

function updateMoqCostBanner(v) {
  if (!v || !v.cost_price) { document.getElementById('moqCostBanner').classList.add('hidden'); return; }
  document.getElementById('moqBaseCost').textContent = fmt(v.cost_price) + ' per ' + v.pack_size + v.unit;
  document.getElementById('moqCostBanner').classList.remove('hidden');
  // Auto-distribute cost proportionally across MOQ rows
  const baseCostPerGram = v.cost_price / toBaseUnit(v.pack_size, v.unit);
  moqRows.forEach(row => {
    const grams = toBaseUnit(row.packSize, row.unit);
    row.packCost = parseFloat((baseCostPerGram * grams).toFixed(4));
  });
  renderMoqTable();
}

// Close dropdown when clicking outside
document.addEventListener('click', (e) => {
  if (!e.target.closest('#productSearch') && !e.target.closest('#productSearchResults')) hideSearchResults();
});

// ════════════════════════════════════════════════════════
//  MAIN UPDATE
// ════════════════════════════════════════════════════════
function updateAll() {
  document.querySelectorAll('.sym-tag').forEach(el => el.textContent = sym());
  document.getElementById('sym-main').textContent = sym();
  const p = compute();
  renderSummaryBar(p);
  const activeTab = document.querySelector('.tab-content:not(.hidden)');
  const tabId = activeTab ? activeTab.id.replace('content-', '') : 'matrix';
  renderTab(tabId, p);
}

function renderTab(tab, p) {
  p = p || compute();
  if (tab === 'matrix')  renderMatrix(p);
  if (tab === 'moq')     renderMoqTable();
  if (tab === 'chain')   renderChain(p);
  if (tab === 'partner') updatePartnerTab();
  if (tab === 'volume')  updateVolumeTab();
  if (tab === 'smart')   updateSmartTab();
}

// ════════════════════════════════════════════════════════
//  SUMMARY BAR
// ════════════════════════════════════════════════════════
function renderSummaryBar(p) {
  const items = [
    { label: 'Cost Price',    val: p.C,   sub: 'Your cost',              color: 'gray'   },
    { label: 'Wholesale',     val: p.ws,  sub: `+${fmtN(p.m.ws*100)}%`, color: 'blue'   },
    { label: 'Visited Store', val: p.vs,  sub: `+${fmtN(p.m.vs*100)}%`, color: 'green'  },
    { label: 'MRP / Retail',  val: p.mrp, sub: `+${fmtN(p.m.mrp*100)}%`,color: 'orange' },
  ];
  document.getElementById('summary-bar').innerHTML = items.map(it => `
    <div class="metric-card bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3 flex items-center justify-between">
      <div>
        <div class="text-xs font-medium text-${it.color}-500">${it.label}</div>
        <div class="text-xl font-bold text-${it.color}-700 num">${it.val > 0 ? fmt(it.val) : '—'}</div>
        <div class="text-xs text-gray-400">${it.sub}</div>
      </div>
    </div>
  `).join('');
}

// ════════════════════════════════════════════════════════
//  TAB 1: PRICE MATRIX
// ════════════════════════════════════════════════════════
function renderMatrix(p) {
  if (p.C <= 0) {
    document.getElementById('tier-cards').innerHTML = '<div class="sm:col-span-2 xl:col-span-5 text-center text-gray-300 py-6 text-sm">Enter cost price above to see all tiers</div>';
    document.getElementById('matrix-tbody').innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-gray-300">Enter cost price above</td></tr>';
    document.getElementById('profit-bar-visual').innerHTML = '<p class="text-gray-300 text-sm text-center">Enter cost price above</p>';
    return;
  }
  const tiers = [
    { key: 'cp',  label: 'Cost Price',         val: p.C,   profitAmt: 0,          bg: 'gray-100',  border: 'gray-200',   text: 'gray-700',   badge: null,            bestFor: 'Internal reference' },
    { key: 'ws',  label: 'Standard Wholesale', val: p.ws,  profitAmt: p.ws-p.C,  bg: 'blue-50',   border: 'blue-200',   text: 'blue-700',   badge: null,            bestFor: 'Distributors, agents, resellers' },
    { key: 'vs',  label: 'Visited Store',       val: p.vs,  profitAmt: p.vs-p.C,  bg: 'green-50',  border: 'green-200',  text: 'green-700',  badge: 'Recommended',   bestFor: 'Field-visited retail stores' },
    { key: 'ps',  label: 'Partner Share',       val: p.ps,  profitAmt: p.psOurProfit, bg: 'purple-50', border: 'purple-200', text: 'purple-700', badge: `${fmtN(p.m.ps*100)}% to partner`, bestFor: 'Revenue-share partnerships' },
    { key: 'mrp', label: 'MRP / Retail',        val: p.mrp, profitAmt: p.mrp-p.C, bg: 'orange-50', border: 'orange-200', text: 'orange-700', badge: 'Consumer Price', bestFor: 'Direct retail / shelf price' },
  ];
  document.getElementById('tier-cards').innerHTML = tiers.map(t => {
    const gm = t.key !== 'cp' ? grossMargin(t.val, p.C) : 0;
    const mk = t.key !== 'cp' ? markupPct(t.val, p.C) : 0;
    const bw = p.mrp > 0 ? Math.min(100, t.val / p.mrp * 100) : 0;
    return `<div class="metric-card bg-${t.bg} rounded-xl border-2 border-${t.border} p-4">
      <div class="flex items-start justify-between mb-2">
        <div class="text-xs font-semibold text-${t.text} uppercase tracking-wide leading-tight">${t.label}</div>
        ${t.badge ? `<span class="recommended-badge ml-1 whitespace-nowrap">${t.badge}</span>` : ''}
      </div>
      <div class="text-2xl font-bold text-${t.text} num mb-1">${fmt(t.val)}</div>
      ${t.key !== 'cp' ? `
        <div class="flex justify-between text-xs text-gray-500 mt-2">
          <span>Profit: <span class="font-semibold text-${t.text} num">${fmt(t.profitAmt)}</span></span>
          <span class="${marginClass(gm)} font-semibold">${pct(gm)} GM</span>
        </div>
        <div class="mt-2 bg-${t.border.replace('200','100')} rounded-full h-1.5">
          <div class="bar-fill ${marginBg(gm)} rounded-full h-1.5" style="width:${bw}%"></div>
        </div>
        <div class="text-xs text-gray-400 mt-1">Markup: +${pct(mk)}</div>
      ` : `<div class="text-xs text-gray-400 mt-2">${t.bestFor}</div>`}
    </div>`;
  }).join('');

  // Profit bar
  const cpW  = (p.C   / p.mrp * 100).toFixed(1);
  const wsP  = ((p.ws  - p.C) / p.mrp * 100).toFixed(1);
  const vsP  = ((p.vs  - p.C) / p.mrp * 100).toFixed(1);
  document.getElementById('profit-bar-visual').innerHTML = `
    <h4 class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-3">Proportional Profit Bar (relative to MRP = 100%)</h4>
    <div class="space-y-2 text-xs">
      <div>
        <div class="flex justify-between text-gray-500 mb-0.5"><span>Standard Wholesale</span><span>${fmt(p.ws)}</span></div>
        <div class="flex h-4 rounded overflow-hidden">
          <div class="bg-gray-300 bar-fill" style="width:${cpW}%"></div>
          <div class="bg-blue-400 bar-fill" style="width:${wsP}%"></div>
          <div class="bg-blue-100 flex-1"></div>
        </div>
        <div class="flex gap-2 mt-0.5 text-gray-400"><span>▪ Cost</span><span class="text-blue-500">▪ Your profit ${fmt(p.ws-p.C)}</span><span class="text-blue-200">▪ Reseller ${fmt(p.mrp-p.ws)}</span></div>
      </div>
      <div>
        <div class="flex justify-between text-gray-500 mb-0.5"><span>Visited Store</span><span>${fmt(p.vs)}</span></div>
        <div class="flex h-4 rounded overflow-hidden">
          <div class="bg-gray-300 bar-fill" style="width:${cpW}%"></div>
          <div class="bg-green-400 bar-fill" style="width:${vsP}%"></div>
          <div class="bg-green-100 flex-1"></div>
        </div>
        <div class="flex gap-2 mt-0.5 text-gray-400"><span>▪ Cost</span><span class="text-green-500">▪ Your profit ${fmt(p.vs-p.C)}</span><span class="text-green-200">▪ Store ${fmt(p.mrp-p.vs)}</span></div>
      </div>
      <div>
        <div class="flex justify-between text-gray-500 mb-0.5"><span>MRP / Retail (direct)</span><span>${fmt(p.mrp)}</span></div>
        <div class="flex h-4 rounded overflow-hidden">
          <div class="bg-gray-300 bar-fill" style="width:${cpW}%"></div>
          <div class="bg-orange-400 flex-1"></div>
        </div>
        <div class="flex gap-2 mt-0.5 text-gray-400"><span>▪ Cost</span><span class="text-orange-500">▪ Full profit ${fmt(p.mrp-p.C)}</span></div>
      </div>
    </div>`;

  const rows = tiers.filter(t => t.key !== 'cp');
  document.getElementById('matrix-tbody').innerHTML = rows.map(t => {
    const gm = grossMargin(t.val, p.C);
    const mk = markupPct(t.val, p.C);
    return `<tr class="border-b border-gray-50 hover:bg-${t.bg} transition">
      <td class="px-4 py-3 font-medium text-${t.text}">${t.label}${t.badge ? `<span class="recommended-badge ml-1">${t.badge}</span>` : ''}</td>
      <td class="px-4 py-3 text-right font-bold text-${t.text} num">${fmt(t.val)}</td>
      <td class="px-4 py-3 text-right text-gray-600">${pct(mk)}</td>
      <td class="px-4 py-3 text-right font-semibold text-green-600 num">${fmt(t.profitAmt)}</td>
      <td class="px-4 py-3 text-right font-semibold ${marginClass(gm)}">${pct(gm)}</td>
      <td class="px-4 py-3 text-right text-xs text-gray-400">${t.bestFor}</td>
    </tr>`;
  }).join('');
}

// ════════════════════════════════════════════════════════
//  TAB 2: MOQ PRICING
// ════════════════════════════════════════════════════════
function renderMoqTable() {
  const tbody = document.getElementById('moq-tbody');
  if (!moqRows.length) {
    tbody.innerHTML = '<tr><td colspan="11" class="text-center text-gray-300 py-8">No rows. Click "+ Add Row" to add pack sizes.</td></tr>';
    return;
  }

  tbody.innerHTML = moqRows.map((row, i) => {
    const rawCostPerGram = cp() > 0 ? cp() / toBaseUnit(
      selectedVariant ? selectedVariant.pack_size : 100,
      selectedVariant ? selectedVariant.unit : 'g'
    ) : 0;

    const gramEquiv   = toBaseUnit(row.packSize, row.unit);
    const rawMaterial = rawCostPerGram * gramEquiv;
    const packCost    = parseFloat(row.packCost) || 0;
    const totalCost   = rawMaterial + packCost;

    // Suggested price based on target margin
    const suggestedPrice = totalCost > 0 && row.targetMargin < 100
      ? totalCost / (1 - row.targetMargin / 100)
      : 0;

    const yourPrice = row.yourPrice !== null && row.yourPrice !== '' ? parseFloat(row.yourPrice) : suggestedPrice;
    const actualMargin = yourPrice > 0 ? grossMargin(yourPrice, totalCost) : 0;
    const profitPerPack = yourPrice - totalCost;

    const highlight = selectedVariant && selectedVariant.pack_size == row.packSize && selectedVariant.unit === row.unit;

    return `<tr class="${highlight ? 'moq-row-highlight' : 'hover:bg-gray-50'} border-b border-gray-100 transition">
      <!-- Pack Size -->
      <td class="px-3 py-2">
        <input type="number" value="${row.packSize}" min="0.001" step="any"
          class="w-24 px-2 py-1 border border-gray-200 rounded text-xs text-center"
          oninput="moqRows[${i}].packSize=parseFloat(this.value)||0; renderMoqTable();" />
      </td>
      <!-- Unit -->
      <td class="px-3 py-2">
        <select class="px-2 py-1 border border-gray-200 rounded text-xs"
          onchange="moqRows[${i}].unit=this.value; renderMoqTable();">
          ${['g','kg','ml','l','pcs'].map(u => `<option ${u===row.unit?'selected':''}>${u}</option>`).join('')}
        </select>
      </td>
      <!-- Packaging cost -->
      <td class="px-3 py-2">
        <input type="number" value="${fmtN(packCost,3)}" min="0" step="0.001"
          class="w-24 px-2 py-1 border border-gray-200 rounded text-xs text-right"
          oninput="moqRows[${i}].packCost=parseFloat(this.value)||0; renderMoqTable();" />
      </td>
      <!-- Raw material cost -->
      <td class="px-3 py-2 text-right text-xs text-gray-500 num">
        ${rawCostPerGram > 0 ? fmt(rawMaterial, 3) : '<span class="text-gray-300">—</span>'}
      </td>
      <!-- Total cost -->
      <td class="px-3 py-2 text-right text-xs font-medium num">
        ${totalCost > 0 ? fmt(totalCost, 3) : '<span class="text-gray-300">Enter CP ↑</span>'}
      </td>
      <!-- Suggested price -->
      <td class="px-3 py-2 text-right text-xs text-blue-600 num">
        ${suggestedPrice > 0 ? fmt(suggestedPrice) : '—'}
      </td>
      <!-- Target margin -->
      <td class="px-3 py-2">
        <div class="flex items-center gap-1">
          <input type="number" value="${row.targetMargin}" min="0" max="99" step="1"
            class="w-16 px-2 py-1 border border-gray-200 rounded text-xs text-center"
            oninput="moqRows[${i}].targetMargin=parseFloat(this.value)||0; renderMoqTable();" />
          <span class="text-xs text-gray-400">%</span>
        </div>
      </td>
      <!-- Your price (editable) -->
      <td class="px-3 py-2">
        <input type="number" value="${row.yourPrice !== null ? row.yourPrice : fmtN(suggestedPrice)}"
          min="0" step="0.01"
          class="w-24 px-2 py-1 border border-orange-200 bg-orange-50 rounded text-xs text-right font-medium"
          oninput="moqRows[${i}].yourPrice=parseFloat(this.value)||0; renderMoqTable();"
          placeholder="${fmtN(suggestedPrice)}" />
      </td>
      <!-- Actual margin -->
      <td class="px-3 py-2 text-right">
        ${totalCost > 0 ? marginBadge(actualMargin) : '<span class="text-gray-300 text-xs">—</span>'}
      </td>
      <!-- Profit per pack -->
      <td class="px-3 py-2 text-right text-xs font-semibold ${profitPerPack >= 0 ? 'text-green-600' : 'text-red-600'} num">
        ${totalCost > 0 ? fmt(profitPerPack) : '—'}
      </td>
      <!-- Delete -->
      <td class="px-3 py-2 text-center no-print">
        <button onclick="moqRows.splice(${i},1); renderMoqTable();" class="text-gray-300 hover:text-red-400 text-xs transition">✕</button>
      </td>
    </tr>`;
  }).join('');

  // Summary cards
  renderMoqSummary();

  // Price-per-gram chart
  renderMoqChart();
}

function renderMoqSummary() {
  const rows = moqRows.filter(r => r.packSize > 0);
  if (!rows.length) { document.getElementById('moqSummary').classList.add('hidden'); return; }

  const rawCostPerGram = cp() > 0 ? cp() / toBaseUnit(
    selectedVariant ? selectedVariant.pack_size : 100,
    selectedVariant ? selectedVariant.unit : 'g'
  ) : 0;

  const data = rows.map(row => {
    const gramEquiv   = toBaseUnit(row.packSize, row.unit);
    const rawMaterial = rawCostPerGram * gramEquiv;
    const totalCost   = rawMaterial + (parseFloat(row.packCost) || 0);
    const suggested   = totalCost > 0 && row.targetMargin < 100 ? totalCost / (1 - row.targetMargin / 100) : 0;
    const price = row.yourPrice !== null ? parseFloat(row.yourPrice) : suggested;
    const margin = price > 0 ? grossMargin(price, totalCost) : 0;
    return { price, totalCost, margin };
  });

  const validData = data.filter(d => d.price > 0 && d.totalCost > 0);
  if (!validData.length) { document.getElementById('moqSummary').classList.add('hidden'); return; }

  const avgMargin = validData.reduce((s, d) => s + d.margin, 0) / validData.length;
  const minMargin = Math.min(...validData.map(d => d.margin));
  const maxMargin = Math.max(...validData.map(d => d.margin));

  document.getElementById('moqSummary').classList.remove('hidden');
  document.getElementById('moqSummary').innerHTML = [
    { label: 'Pack Sizes Configured', val: rows.length, sub: 'rows', color: 'gray' },
    { label: 'Avg Margin',  val: fmtN(avgMargin) + '%', sub: 'across all sizes', color: avgMargin >= 20 ? 'green' : avgMargin >= 10 ? 'yellow' : 'red' },
    { label: 'Min Margin',  val: fmtN(minMargin) + '%', sub: 'thinnest pack', color: minMargin >= 10 ? 'blue' : 'red' },
    { label: 'Max Margin',  val: fmtN(maxMargin) + '%', sub: 'premium pack', color: 'orange' },
  ].map(c => `
    <div class="metric-card bg-${c.color}-50 rounded-xl border border-${c.color}-100 p-3 text-center">
      <div class="text-xs text-${c.color}-400 uppercase font-medium mb-0.5">${c.label}</div>
      <div class="text-xl font-bold text-${c.color}-700">${c.val}</div>
      <div class="text-xs text-${c.color}-400">${c.sub}</div>
    </div>
  `).join('');
}

function renderMoqChart() {
  const rawCostPerGram = cp() > 0 ? cp() / toBaseUnit(
    selectedVariant ? selectedVariant.pack_size : 100,
    selectedVariant ? selectedVariant.unit : 'g'
  ) : null;

  const rows = moqRows.filter(r => r.packSize > 0);
  const labels = rows.map(r => r.packSize + r.unit);
  const pricePerGram = rows.map(r => {
    const gramEquiv   = toBaseUnit(r.packSize, r.unit);
    const rawMaterial = rawCostPerGram ? rawCostPerGram * gramEquiv : 0;
    const totalCost   = rawMaterial + (parseFloat(r.packCost) || 0);
    const suggested   = totalCost > 0 && r.targetMargin < 100 ? totalCost / (1 - r.targetMargin / 100) : 0;
    const price = r.yourPrice !== null ? parseFloat(r.yourPrice) : suggested;
    return gramEquiv > 0 ? price / gramEquiv : 0;
  });
  const margins = rows.map(r => {
    const gramEquiv   = toBaseUnit(r.packSize, r.unit);
    const rawMaterial = rawCostPerGram ? rawCostPerGram * gramEquiv : 0;
    const totalCost   = rawMaterial + (parseFloat(r.packCost) || 0);
    const suggested   = totalCost > 0 && r.targetMargin < 100 ? totalCost / (1 - r.targetMargin / 100) : 0;
    const price = r.yourPrice !== null ? parseFloat(r.yourPrice) : suggested;
    return totalCost > 0 ? grossMargin(price, totalCost) : 0;
  });

  if (moqChartInstance) moqChartInstance.destroy();
  const ctx = document.getElementById('moqChart').getContext('2d');
  moqChartInstance = new Chart(ctx, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        {
          label: 'Price per gram',
          data: pricePerGram,
          backgroundColor: '#fb923c',
          borderRadius: 4,
          yAxisID: 'y',
        },
        {
          label: 'Gross Margin %',
          data: margins,
          type: 'line',
          borderColor: '#16a34a',
          backgroundColor: 'rgba(22,163,74,0.1)',
          borderWidth: 2,
          pointRadius: 4,
          tension: 0.3,
          fill: false,
          yAxisID: 'y2',
        },
      ]
    },
    options: {
      responsive: true,
      plugins: {
        tooltip: {
          callbacks: {
            label: c => c.dataset.label + ': ' + (c.datasetIndex === 0 ? sym() + ' ' + c.raw.toFixed(4) : c.raw.toFixed(1) + '%')
          }
        }
      },
      scales: {
        y: { title: { display: true, text: 'Price / gram' }, ticks: { callback: v => sym() + v.toFixed(3) } },
        y2: { position: 'right', title: { display: true, text: 'Margin %' }, ticks: { callback: v => v + '%' }, grid: { drawOnChartArea: false } }
      }
    }
  });
}

function addMoqRow() {
  moqRows.push({ packSize: 1, unit: 'g', packCost: 0, targetMargin: 30, yourPrice: null });
  renderMoqTable();
}

function resetMoqRows() {
  moqRows = JSON.parse(JSON.stringify(DEFAULT_MOQ_ROWS));
  if (selectedVariant) updateMoqCostBanner(selectedVariant);
  else renderMoqTable();
}

// ════════════════════════════════════════════════════════
//  TAB 3: CHAIN ANALYSIS
// ════════════════════════════════════════════════════════
function renderChain(p) {
  if (p.C <= 0) {
    document.getElementById('value-chain-cards').innerHTML = '<p class="text-gray-300 text-sm">Enter cost price above</p>';
    document.getElementById('channel-roi-table').innerHTML = '<p class="text-gray-300 text-sm">Enter cost price above</p>';
    document.getElementById('strategy-insight').classList.add('hidden');
    if (chainChartInstance) { chainChartInstance.destroy(); chainChartInstance = null; }
    return;
  }
  if (chainChartInstance) chainChartInstance.destroy();
  const ctx = document.getElementById('chainChart').getContext('2d');
  chainChartInstance = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: ['Wholesale', 'Visited Store', 'Partner Share', 'MRP / Retail'],
      datasets: [
        { label: 'Cost (CP)',               data: [p.C, p.C, p.C, p.C],                    backgroundColor: '#94a3b8' },
        { label: 'Your Profit',             data: [p.ws-p.C, p.vs-p.C, p.psOurProfit, p.mrp-p.C], backgroundColor: ['#60a5fa','#4ade80','#a78bfa','#fb923c'] },
        { label: 'Retailer / Partner',      data: [p.mrp-p.ws, p.mrp-p.vs, p.partnerEarns, 0],   backgroundColor: ['#bfdbfe','#bbf7d0','#ede9fe','#fed7aa'] },
      ]
    },
    options: { responsive: true, scales: { x: { stacked: true }, y: { stacked: true, ticks: { callback: v => sym() + v } } }, plugins: { tooltip: { callbacks: { label: c => c.dataset.label + ': ' + fmt(c.raw) } } } }
  });

  const channels = [
    { name: 'Standard Wholesale', price: p.ws, yourProfit: p.ws-p.C, nextLabel: 'Distributor earns', nextAmt: p.mrp-p.ws, color: 'blue' },
    { name: 'Visited Store',      price: p.vs, yourProfit: p.vs-p.C, nextLabel: 'Retail store earns', nextAmt: p.mrp-p.vs, color: 'green' },
    { name: 'Partner Share',      price: p.ps, yourProfit: p.psOurProfit, nextLabel: `Partner earns (${fmtN(p.m.ps*100)}%)`, nextAmt: p.partnerEarns, color: 'purple' },
  ];
  document.getElementById('value-chain-cards').innerHTML = channels.map(c => {
    const ys = p.totalProfit > 0 ? (c.yourProfit/p.totalProfit*100) : 0;
    const ns = p.totalProfit > 0 ? (c.nextAmt/p.totalProfit*100) : 0;
    return `<div class="bg-${c.color}-50 rounded-xl p-4 border border-${c.color}-100">
      <div class="font-semibold text-${c.color}-800 text-sm mb-2">${c.name}</div>
      <div class="grid grid-cols-2 gap-2 text-xs mb-2">
        <div class="text-gray-500">You sell at <span class="font-bold text-${c.color}-700 num">${fmt(c.price)}</span></div>
        <div class="text-gray-500">Your profit <span class="font-bold text-green-600 num">${fmt(c.yourProfit)}</span> <span class="text-gray-400">(${fmtN(ys)}% of chain)</span></div>
      </div>
      <div class="text-xs text-gray-400 mb-2">${c.nextLabel}: <span class="font-medium text-gray-500 num">${fmt(c.nextAmt)}</span> <span class="text-gray-400">(${fmtN(ns)}%)</span></div>
    </div>`;
  }).join('');

  const roiRows = [
    { ch: 'Standard Wholesale', price: p.ws,  yourP: p.ws-p.C,      nextP: p.mrp-p.ws  },
    { ch: 'Visited Store',      price: p.vs,  yourP: p.vs-p.C,      nextP: p.mrp-p.vs  },
    { ch: 'Partner Share',      price: p.ps,  yourP: p.psOurProfit, nextP: p.partnerEarns },
    { ch: 'MRP / Retail',       price: p.mrp, yourP: p.mrp-p.C,     nextP: 0           },
  ];
  document.getElementById('channel-roi-table').innerHTML = `<table class="w-full text-sm">
    <thead><tr class="bg-gray-50 text-gray-400 text-xs uppercase"><th class="text-left px-4 py-2 font-medium">Channel</th><th class="text-right px-4 py-2 font-medium">Selling Price</th><th class="text-right px-4 py-2 font-medium">Your Profit</th><th class="text-right px-4 py-2 font-medium">Your Share %</th><th class="text-right px-4 py-2 font-medium">Next Party</th><th class="text-right px-4 py-2 font-medium">Gross Margin</th></tr></thead>
    <tbody>${roiRows.map(r => {
      const ys = p.totalProfit > 0 ? (r.yourP/p.totalProfit*100) : 0;
      const gm = grossMargin(r.price, p.C);
      return `<tr class="border-b border-gray-50 hover:bg-gray-50 transition">
        <td class="px-4 py-3 font-medium text-gray-700">${r.ch}</td>
        <td class="px-4 py-3 text-right font-bold text-gray-700 num">${fmt(r.price)}</td>
        <td class="px-4 py-3 text-right font-semibold text-green-600 num">${fmt(r.yourP)}</td>
        <td class="px-4 py-3 text-right font-medium">${fmtN(ys)}%</td>
        <td class="px-4 py-3 text-right text-gray-500 num">${r.nextP > 0 ? fmt(r.nextP) : '—'}</td>
        <td class="px-4 py-3 text-right font-semibold ${marginClass(gm)}">${pct(gm)}</td>
      </tr>`;
    }).join('')}</tbody>
  </table>`;

  const insight = document.getElementById('strategy-insight');
  insight.classList.remove('hidden');
  document.getElementById('strategy-text').innerHTML = [
    `📦 <strong>Standard Wholesale</strong> earns <strong>${fmtN(grossMargin(p.ws,p.C))}% gross margin</strong> — best for volume with minimal field effort.`,
    `🤝 <strong>Visited Store</strong> earns ${fmt(p.vs-p.ws)} more per unit vs standard wholesale.`,
    `💎 <strong>MRP / Direct Retail</strong> captures <strong>100% of chain profit</strong> (${fmt(p.mrp-p.C)}/unit) — use for direct sales.`,
  ].map(l => `<p>${l}</p>`).join('');
}

// ════════════════════════════════════════════════════════
//  TAB 4: PARTNER PITCH
// ════════════════════════════════════════════════════════
function updatePartnerTab() {
  const p = compute();
  const buyMode  = document.getElementById('partnerBuyMode').value;
  const sellMode = document.getElementById('partnerSellMode').value;
  document.getElementById('custom-buy-div').classList.toggle('hidden', buyMode !== 'custom');
  document.getElementById('custom-sell-div').classList.toggle('hidden', sellMode !== 'custom');

  const buyPriceMap = { ws: p.ws, vs: p.vs, ps: p.ps, custom: parseFloat(document.getElementById('customPartnerBuy').value) || 0 };
  const buyPrice  = buyPriceMap[buyMode] || 0;
  const sellPrice = sellMode === 'mrp' ? p.mrp : (parseFloat(document.getElementById('customPartnerSell').value) || 0);
  const partnerProfit = sellPrice - buyPrice;
  const partnerGM = grossMargin(sellPrice, buyPrice);
  const ourProfit = buyPrice - p.C;
  const ourGM     = grossMargin(buyPrice, p.C);
  const daily = parseInt(document.getElementById('dailyUnits').value) || 5;
  const days  = parseInt(document.getElementById('opDays').value) || 26;
  const monthlyPartner = partnerProfit * daily * days;
  const monthlyOur     = ourProfit * daily * days;

  document.getElementById('partner-metrics').innerHTML = [
    { label: 'Partner Buys At',       val: buyPrice,      sub: 'What you charge them',      color: 'purple' },
    { label: 'Partner Sells At',       val: sellPrice,     sub: 'End-consumer price',        color: 'blue'   },
    { label: 'Partner Profit / Unit',  val: partnerProfit, sub: pct(partnerGM) + ' margin',  color: partnerProfit > 0 ? 'green' : 'red' },
    { label: 'Your Profit / Unit',     val: ourProfit,     sub: pct(ourGM) + ' margin',      color: 'orange' },
  ].map(m => `<div class="metric-card bg-${m.color}-50 rounded-xl border border-${m.color}-100 p-4 text-center"><div class="text-xs text-${m.color}-400 font-medium uppercase mb-1">${m.label}</div><div class="text-xl font-bold text-${m.color}-700 num">${m.val > 0 ? fmt(m.val) : '—'}</div><div class="text-xs text-${m.color}-400">${m.sub}</div></div>`).join('');

  document.getElementById('monthly-partner-profit').textContent = partnerProfit > 0 ? fmt(monthlyPartner) : '—';
  document.getElementById('monthly-formula').textContent = `${daily} × ${days} days × ${fmt(partnerProfit)}`;

  const projUnits = [2, 5, 10, 20, 50, 100];
  document.getElementById('projection-tbody').innerHTML = projUnits.map(u => {
    const total = u * days;
    const hi = u === daily ? 'bg-purple-50 font-semibold' : '';
    return `<tr class="${hi} border-t border-purple-100 text-xs"><td class="py-1.5">${u}/day</td><td class="text-right num">${total}</td><td class="text-right num">${fmt(sellPrice * total)}</td><td class="text-right num text-green-600">${partnerProfit > 0 ? fmt(partnerProfit * total) : '—'}</td><td class="text-right num text-orange-600">${ourProfit > 0 ? fmt(ourProfit * total) : '—'}</td></tr>`;
  }).join('');

  const pName = selectedProduct ? selectedProduct.name : document.getElementById('productSearch').value;
  document.getElementById('pitch-product-name').textContent = pName ? `Product: ${pName}` : '';

  if (p.C > 0 && buyPrice > 0 && sellPrice > 0 && partnerProfit > 0) {
    document.getElementById('partner-card-content').innerHTML = `
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5 text-left">
        <div class="bg-white rounded-xl border border-gray-200 p-4 text-center"><div class="text-xs text-gray-400 mb-1">You Purchase From Us At</div><div class="text-2xl font-bold text-purple-700 num">${fmt(buyPrice)}</div><div class="text-xs text-gray-400">per unit</div></div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 text-center"><div class="text-xs text-gray-400 mb-1">Recommended Selling Price</div><div class="text-2xl font-bold text-blue-700 num">${fmt(sellPrice)}</div><div class="text-xs text-gray-400">per unit (MRP)</div></div>
        <div class="bg-green-100 rounded-xl border border-green-300 p-4 text-center"><div class="text-xs text-green-600 font-semibold mb-1">YOUR PROFIT PER UNIT</div><div class="text-3xl font-extrabold text-green-700 num">${fmt(partnerProfit)}</div><div class="text-xs text-green-500">${pct(partnerGM)} margin</div></div>
      </div>
      <div class="bg-gradient-to-r from-orange-500 to-orange-400 text-white rounded-xl p-5 mb-4">
        <div class="text-sm font-semibold mb-1 text-orange-100">If you sell just ${daily} units/day (${daily*days}/month):</div>
        <div class="text-4xl font-extrabold num">${fmt(monthlyPartner)}</div>
        <div class="text-orange-100 text-sm mt-1">Monthly profit for your store</div>
      </div>`;
  } else {
    document.getElementById('partner-card-content').innerHTML = '<p class="text-gray-300 py-4">Enter cost price and configure partner pricing above</p>';
  }
}

// ════════════════════════════════════════════════════════
//  TAB 5: VOLUME CALCULATOR
// ════════════════════════════════════════════════════════
function updateVolumeTab() {
  const p = compute();
  const ch    = document.getElementById('volChannel').value;
  const units = parseInt(document.getElementById('volUnits').value) || 0;
  const mm    = parseFloat(document.getElementById('minMargin').value) || 0;
  const priceMap = { ws: p.ws, vs: p.vs, ps: p.ps, mrp: p.mrp };
  const price = priceMap[ch] || 0;
  const profitPU = price - p.C;
  const gm = grossMargin(price, p.C);

  document.getElementById('vol-revenue').textContent  = p.C > 0 ? fmt(price * units) : '—';
  document.getElementById('vol-price-tag').textContent = `at ${fmt(price)}/unit`;
  document.getElementById('vol-profit').textContent    = p.C > 0 ? fmt(profitPU * units) : '—';
  document.getElementById('vol-ppu').textContent       = `${fmt(profitPU)}/unit`;
  document.getElementById('vol-cost').textContent      = p.C > 0 ? fmt(p.C * units) : '—';
  document.getElementById('vol-cp-tag').textContent    = `at ${fmt(p.C)}/unit`;
  document.getElementById('vol-margin').textContent    = p.C > 0 ? pct(gm) : '—';
  document.getElementById('vol-unit-economics').textContent = p.C > 0 ? `Per unit: ${fmt(price)} − ${fmt(p.C)} = ${fmt(profitPU)} (${pct(gm)})` : '— enter cost price above —';

  if (p.C > 0) {
    const minPrice = p.C / (1 - mm / 100);
    const maxDisc  = price - minPrice;
    const maxPct   = price > 0 ? (maxDisc / price * 100) : 0;
    document.getElementById('max-discount-pct').textContent = maxDisc > 0
      ? `${pct(maxPct)} (${fmt(maxDisc)}/unit) — floor: ${fmt(minPrice)}`
      : 'No room at this margin floor';
  }

  if (p.C <= 0) { document.getElementById('vol-comparison-tbody').innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-gray-300">Enter cost price above</td></tr>'; return; }
  const qtys = [10, 25, 50, 100, 250, 500, 1000];
  document.getElementById('vol-comparison-tbody').innerHTML = qtys.map(q => {
    const hi = q === units ? 'bg-orange-50 font-semibold' : 'hover:bg-gray-50';
    return `<tr class="${hi} border-b border-gray-50 transition text-xs"><td class="px-4 py-2.5 font-medium">${q.toLocaleString()}</td><td class="px-4 py-2.5 text-right num">${fmt(p.ws*q)}</td><td class="px-4 py-2.5 text-right num">${fmt(p.vs*q)}</td><td class="px-4 py-2.5 text-right num font-semibold text-orange-600">${fmt(p.mrp*q)}</td><td class="px-4 py-2.5 text-right num text-green-600">${fmt((p.ws-p.C)*q)}</td><td class="px-4 py-2.5 text-right num text-green-600">${fmt((p.vs-p.C)*q)}</td><td class="px-4 py-2.5 text-right num text-green-700 font-semibold">${fmt((p.mrp-p.C)*q)}</td></tr>`;
  }).join('');
}

// ════════════════════════════════════════════════════════
//  TAB 6: SMART TOOLS
// ════════════════════════════════════════════════════════
function updateSmartTab() {
  const p = compute();
  document.getElementById('sm-cp-show').textContent = p.C > 0 ? fmt(p.C) : 'Enter CP above ↑';

  const tgtM = parseFloat(document.getElementById('targetMarginPct').value) || 0;
  const tgtPrice  = p.C > 0 && tgtM < 100 ? p.C / (1 - tgtM / 100) : 0;
  document.getElementById('sm-target-price').textContent  = tgtPrice > 0 ? fmt(tgtPrice) : '—';
  document.getElementById('sm-target-profit').textContent = fmt(tgtPrice - p.C);
  document.getElementById('sm-target-markup').textContent = pct(markupPct(tgtPrice, p.C));

  const compPrice = parseFloat(document.getElementById('competitorPrice').value) || 0;
  const compEl    = document.getElementById('comp-analysis');
  if (compPrice > 0 && p.C > 0) {
    const diff = ((p.mrp - compPrice) / compPrice * 100);
    const compGM = grossMargin(compPrice, p.C);
    compEl.innerHTML = `<div class="space-y-2">
      <div class="flex justify-between"><span class="text-gray-500">Our MRP vs Competitor:</span><span class="font-bold ${diff > 0 ? 'text-red-600' : 'text-green-600'}">${diff > 0 ? '↑' : '↓'} ${Math.abs(diff).toFixed(1)}% ${diff > 0 ? 'higher' : 'lower'}</span></div>
      <div class="flex justify-between"><span class="text-gray-500">If we match their price:</span><span class="font-medium">Margin = <span class="${marginClass(compGM)}">${pct(compGM)}</span></span></div>
      <div class="pt-1 border-t border-gray-100 text-gray-600 ${diff > 0 ? 'bg-red-50 p-2 rounded' : 'bg-green-50 p-2 rounded'}">${diff > 0 ? '⚠️ Our MRP is higher. Justify with quality or reduce markup.' : '✅ We are priced below competitor — room to raise MRP.'}</div>
    </div>`;
  } else compEl.innerHTML = '<p class="text-gray-400">Enter competitor\'s price and your cost price</p>';

  const dir   = document.getElementById('cpChangeDir').value;
  const chgPct = parseFloat(document.getElementById('cpChangePct').value) || 0;
  const cpImp = document.getElementById('cp-impact-table');
  if (p.C > 0) {
    const newCP  = dir === 'up' ? p.C*(1+chgPct/100) : p.C*(1-chgPct/100);
    const m = markups();
    cpImp.innerHTML = `<div class="space-y-1.5">
      <div class="flex justify-between"><span class="text-gray-500">New CP:</span><span class="${dir==='up'?'text-red-600':'text-green-600'} font-bold num">${fmt(newCP)} <span class="text-gray-400 font-normal text-xs">(was ${fmt(p.C)})</span></span></div>
      <div class="flex justify-between"><span class="text-gray-500">New Wholesale:</span><span class="num">${fmt(newCP*(1+m.ws))}</span></div>
      <div class="flex justify-between"><span class="text-gray-500">New Visited Store:</span><span class="num">${fmt(newCP*(1+m.vs))}</span></div>
      <div class="flex justify-between"><span class="text-gray-500">New MRP:</span><span class="font-bold text-orange-700 num">${fmt(newCP*(1+m.mrp))}</span></div>
    </div>`;
  } else cpImp.innerHTML = '<p class="text-gray-400">Enter cost price above</p>';

  const taxRate = parseFloat(document.getElementById('taxRate').value) || 0;
  const taxEl   = document.getElementById('tax-price-table');
  if (p.C > 0) {
    taxEl.innerHTML = `<div class="space-y-1.5">
      <div class="flex justify-between"><span class="text-gray-500">Wholesale + ${taxRate}% tax:</span><span class="num">${fmt(p.ws*(1+taxRate/100))}</span></div>
      <div class="flex justify-between"><span class="text-gray-500">Visited Store + ${taxRate}% tax:</span><span class="num">${fmt(p.vs*(1+taxRate/100))}</span></div>
      <div class="flex justify-between"><span class="text-gray-500">MRP + ${taxRate}% tax:</span><span class="font-bold text-orange-600 num">${fmt(p.mrp*(1+taxRate/100))}</span></div>
      <div class="flex justify-between pt-1 border-t border-gray-100 text-gray-400"><span>Tax on MRP:</span><span class="num">${fmt(p.mrp*taxRate/100)}</span></div>
    </div>`;
  } else taxEl.innerHTML = '<p class="text-gray-400">Enter cost price above</p>';

  const fixed  = parseFloat(document.getElementById('fixedCosts').value) || 0;
  const beCh   = document.getElementById('beChannel').value;
  const beP    = { ws: p.ws, vs: p.vs, mrp: p.mrp }[beCh] || 0;
  const bePPU  = beP - p.C;
  if (fixed > 0 && bePPU > 0) {
    const beUnits = Math.ceil(fixed / bePPU);
    document.getElementById('be-units').textContent   = beUnits.toLocaleString();
    document.getElementById('be-revenue').textContent = 'Revenue: ' + fmt(beUnits * beP);
  } else {
    document.getElementById('be-units').textContent   = '—';
    document.getElementById('be-revenue').textContent = 'Enter fixed costs above';
  }
}

function applyTaxPreset() {
  const v = document.getElementById('taxPreset').value;
  if (v !== 'custom') { document.getElementById('taxRate').value = v; updateSmartTab(); }
}

// ════════════════════════════════════════════════════════
//  TAB SWITCHING
// ════════════════════════════════════════════════════════
function switchTab(tab) {
  document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
  document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('tab-active'));
  document.getElementById('content-' + tab).classList.remove('hidden');
  document.getElementById('tab-' + tab).classList.add('tab-active');
  renderTab(tab);
}

// ════════════════════════════════════════════════════════
//  SAVED PRESETS (localStorage)
// ════════════════════════════════════════════════════════
function saveCurrentProduct() {
  const name = document.getElementById('productSearch').value.trim() || 'Unnamed';
  const cost = parseFloat(document.getElementById('costPrice').value) || 0;
  if (cost <= 0) { alert('Enter cost price before saving.'); return; }
  const preset = {
    name, cost,
    currency: document.getElementById('currency').value,
    wsM: document.getElementById('wholesaleMarkup').value,
    vsM: document.getElementById('visitedMarkup').value,
    mrpM: document.getElementById('mrpMarkup').value,
    psM: document.getElementById('partnerShare').value,
    id: Date.now(),
  };
  const ex = savedProducts.findIndex(p => p.name === name);
  if (ex >= 0) savedProducts[ex] = preset; else savedProducts.push(preset);
  if (savedProducts.length > 8) savedProducts.shift();
  localStorage.setItem('rp_products', JSON.stringify(savedProducts));
  renderSavedPills();
}

function loadPreset(id) {
  const p = savedProducts.find(p => p.id === id);
  if (!p) return;
  document.getElementById('productSearch').value = p.name;
  document.getElementById('costPrice').value = p.cost;
  document.getElementById('currency').value = p.currency || 'NPR';
  document.getElementById('wholesaleMarkup').value = p.wsM;
  document.getElementById('visitedMarkup').value = p.vsM;
  document.getElementById('mrpMarkup').value = p.mrpM;
  document.getElementById('partnerShare').value = p.psM;
  renderSavedPills(id);
  updateAll();
}

function renderSavedPills(activeId) {
  document.getElementById('savedPillsRow').innerHTML = savedProducts.map(p => `
    <span class="product-pill ${p.id === activeId ? 'active-pill' : ''}" onclick="loadPreset(${p.id})">${p.name}</span>
  `).join('');
}

function resetMarkups() {
  document.getElementById('wholesaleMarkup').value = 13;
  document.getElementById('visitedMarkup').value   = 15;
  document.getElementById('mrpMarkup').value       = 25;
  document.getElementById('partnerShare').value    = 30;
  updateAll();
}

// ════════════════════════════════════════════════════════
//  INIT
// ════════════════════════════════════════════════════════
renderSavedPills();
renderMoqTable();
updateAll();
</script>
</body>
</html>
