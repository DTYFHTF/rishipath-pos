<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>RishiPath — Pricing Calculator</title>
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
    body { font-family: 'Inter', system-ui, sans-serif; background: #f8f7f4; }
    .tab-active { border-bottom: 3px solid #ea580c; color: #ea580c; font-weight: 600; }
    .tab-btn:not(.tab-active):hover { color: #374151; }
    .num { font-variant-numeric: tabular-nums; }
    @media print { .no-print { display: none !important; } }
    #productSearchResults {
      position: absolute; left: 0; right: 0; top: 100%; z-index: 50;
      background: white; border: 1px solid #e5e7eb; border-top: none;
      border-radius: 0 0 8px 8px; max-height: 280px; overflow-y: auto;
      box-shadow: 0 4px 16px rgba(0,0,0,.10);
    }
    .s-item { padding: 9px 12px; cursor: pointer; border-bottom: 1px solid #f9fafb; }
    .s-item:hover { background: #fff7ed; }
    .s-item:last-child { border: none; }
    .vpill { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 99px; font-size: 12px;
             border: 1.5px solid #e5e7eb; background: white; cursor: pointer; transition: all .15s; }
    .vpill:hover, .vpill.active { background: #fff7ed; border-color: #ea580c; color: #ea580c; }
    .gradient-bg { background: linear-gradient(135deg, #ea580c 0%, #c2410c 100%); }
    ::-webkit-scrollbar { width: 5px; } ::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 99px; }
    .pack-row-highlight { background: #fff7ed; }
    .tier-badge { display: inline-flex; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 600; }
    .margin-great { background: #dcfce7; color: #166534; }
    .margin-ok    { background: #fef9c3; color: #713f12; }
    .margin-low   { background: #fee2e2; color: #991b1b; }
    input[type=number]::-webkit-inner-spin-button { opacity: .6; }
    .blend-row td { padding: 4px 6px; }
    .b-drop {
      position: absolute; left: 0; right: 0; top: 100%; z-index: 60;
      background: white; border: 1px solid #e5e7eb; border-radius: 0 0 8px 8px;
      max-height: 220px; overflow-y: auto; box-shadow: 0 4px 16px rgba(0,0,0,.10);
    }
    .chip { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 99px;
            font-size: 12px; border: 1.5px dashed #86efac; background: #f0fdf4; color: #15803d;
            cursor: pointer; transition: all .15s; }
    .chip:hover { background: #dcfce7; border-style: solid; }
  </style>
</head>
<body class="min-h-screen">

<!-- HEADER -->
<header class="gradient-bg text-white shadow-md no-print sticky top-0 z-50">
  <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <span class="text-2xl">🌿</span>
      <div>
        <div class="font-bold text-base leading-tight">RishiPath Pricing</div>
        <div class="text-orange-200 text-xs">Shuddhidham Product Pricing Tool</div>
      </div>
    </div>
    <div class="flex items-center gap-2">
      <select id="currency" onchange="refreshAll()"
        class="bg-white/20 text-white border border-white/30 rounded-lg px-2 py-1.5 text-sm">
        <option value="NPR">रू NPR</option>
        <option value="INR">₹ INR</option>
        <option value="USD">$ USD</option>
      </select>
      <button onclick="window.print()" class="bg-white/20 border border-white/30 rounded-lg px-3 py-1.5 text-sm hover:bg-white/30">🖨️ Print</button>
      @auth
      <a href="/admin" class="bg-white/20 border border-white/30 rounded-lg px-3 py-1.5 text-sm hover:bg-white/30">← Admin</a>
      @endauth
    </div>
  </div>
</header>

<div class="max-w-6xl mx-auto px-4 py-5 space-y-4">

  <!-- PRODUCT / COST PANEL -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

      <!-- Product search -->
      <div class="relative sm:col-span-2">
        <label class="block text-xs font-medium text-gray-500 mb-1">Search Product <span class="text-gray-400 font-normal">(optional — auto-fills cost)</span></label>
        <input id="productSearch" type="text" placeholder="Type product name or SKU…"
          autocomplete="off" oninput="onSearch()" onfocus="onSearchFocus()"
          class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" />
        <div id="productSearchResults" class="hidden"></div>
      </div>

      <!-- Cost price -->
      <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">
          Your Cost Price
          <span class="text-gray-400 font-normal" id="cpUnitLabel">— per unit</span>
        </label>
        <div class="relative">
          <span id="symMain" class="absolute left-3 top-2.5 text-gray-400 text-sm">रू</span>
          <input id="costPrice" type="number" placeholder="0.00" min="0" step="0.01"
            class="w-full pl-8 pr-3 py-2 border border-gray-200 rounded-lg text-sm num"
            oninput="refreshAll()" />
        </div>
        <div id="cpNote" class="text-xs text-gray-400 mt-0.5"></div>
      </div>
    </div>

    <!-- Variant pills -->
    <div id="variantWrap" class="hidden mt-3">
      <label class="block text-xs font-medium text-gray-500 mb-1">Choose Pack Size</label>
      <div id="variantPills" class="flex flex-wrap gap-2"></div>
    </div>

    <!-- Selected product info -->
    <div id="selInfo" class="hidden mt-3 bg-orange-50 border border-orange-100 rounded-lg px-3 py-2 text-xs text-orange-700 flex items-center justify-between">
      <span id="selInfoText"></span>
      <button onclick="clearProduct()" class="text-orange-400 hover:text-orange-600">&#x2715; Clear</button>
    </div>
  </div>

  <!-- QUICK PRICE BAR -->
  <div id="quickBar" class="grid grid-cols-2 sm:grid-cols-4 gap-3"></div>

  <!-- TABS -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="flex border-b border-gray-100 overflow-x-auto no-print bg-gray-50 text-sm">
      <button id="tab-tiers"  onclick="switchTab('tiers')"  class="tab-btn tab-active px-4 py-3.5 whitespace-nowrap">&#x1F4CA; Pricing Tiers</button>
      <button id="tab-packs"  onclick="switchTab('packs')"  class="tab-btn px-4 py-3.5 text-gray-500 whitespace-nowrap">&#x1F4E6; Pack Pricing</button>
      <button id="tab-blend"  onclick="switchTab('blend')"  class="tab-btn px-4 py-3.5 text-gray-500 whitespace-nowrap">&#x1F9C2; Blend / Mix</button>
      <button id="tab-vol"    onclick="switchTab('vol')"    class="tab-btn px-4 py-3.5 text-gray-500 whitespace-nowrap">&#x1F4C8; Volume</button>
    </div>

    <!-- TAB 1: PRICING TIERS -->
    <div id="content-tiers" class="tab-content p-5">
      <p class="text-xs text-gray-400 mb-4">Set your cost price above. This shows the recommended selling price for each channel.</p>
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
        <div class="bg-blue-50 rounded-xl p-3 border border-blue-100">
          <div class="text-xs text-blue-600 font-semibold mb-1">Wholesale</div>
          <div class="flex items-center gap-1">
            <span class="text-xs text-gray-400">CP +</span>
            <input id="wsM" type="number" value="13" min="0" max="500" step="0.5"
              class="w-14 px-2 py-1 border border-blue-200 rounded text-sm text-center"
              oninput="refreshAll()" />
            <span class="text-xs text-gray-400">%</span>
          </div>
          <div class="text-[10px] text-blue-400 mt-1">Distributors &amp; agents</div>
        </div>
        <div class="bg-green-50 rounded-xl p-3 border border-green-100">
          <div class="text-xs text-green-600 font-semibold mb-1">Store Price</div>
          <div class="flex items-center gap-1">
            <span class="text-xs text-gray-400">CP +</span>
            <input id="vsM" type="number" value="20" min="0" max="500" step="0.5"
              class="w-14 px-2 py-1 border border-green-200 rounded text-sm text-center"
              oninput="refreshAll()" />
            <span class="text-xs text-gray-400">%</span>
          </div>
          <div class="text-[10px] text-green-400 mt-1">Direct retail stores</div>
        </div>
        <div class="bg-orange-50 rounded-xl p-3 border border-orange-100">
          <div class="text-xs text-orange-600 font-semibold mb-1">Retail / MRP</div>
          <div class="flex items-center gap-1">
            <span class="text-xs text-gray-400">CP +</span>
            <input id="mrpM" type="number" value="30" min="0" max="500" step="0.5"
              class="w-14 px-2 py-1 border border-orange-200 rounded text-sm text-center"
              oninput="refreshAll()" />
            <span class="text-xs text-gray-400">%</span>
          </div>
          <div class="text-[10px] text-orange-400 mt-1">End consumer price</div>
        </div>
        <div class="bg-purple-50 rounded-xl p-3 border border-purple-100">
          <div class="text-xs text-purple-600 font-semibold mb-1">Online / Premium</div>
          <div class="flex items-center gap-1">
            <span class="text-xs text-gray-400">CP +</span>
            <input id="premM" type="number" value="50" min="0" max="500" step="0.5"
              class="w-14 px-2 py-1 border border-purple-200 rounded text-sm text-center"
              oninput="refreshAll()" />
            <span class="text-xs text-gray-400">%</span>
          </div>
          <div class="text-[10px] text-purple-400 mt-1">Online / gift packs</div>
        </div>
      </div>
      <div id="tiersTable"><p class="text-gray-300 text-sm text-center py-8">Enter cost price above to see pricing</p></div>
    </div>

    <!-- TAB 2: PACK PRICING -->
    <div id="content-packs" class="tab-content hidden p-5">
      <div class="flex items-start justify-between gap-3 mb-4 flex-wrap">
        <div>
          <h3 class="font-semibold text-gray-800 text-sm mb-1">Pack Size Pricing</h3>
          <p class="text-xs text-gray-400">Given cost for any base unit, this shows the recommended price for smaller or larger packs. Smaller packs need higher margins to stay profitable.</p>
        </div>
        <div class="flex gap-2 no-print">
          <button onclick="addPackRow()" class="text-xs bg-orange-50 text-orange-600 border border-orange-200 rounded-lg px-3 py-1.5 hover:bg-orange-100">+ Add Size</button>
          <button onclick="resetPackRows()" class="text-xs text-gray-400 hover:text-gray-600">Reset</button>
        </div>
      </div>

      <div id="packCostNote" class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-2 mb-4 text-xs text-amber-800 hidden">
        &#x1F4CC; Base: <strong id="packCostNoteText"></strong>
      </div>

      <div class="bg-orange-50 border border-orange-100 rounded-xl p-4 mb-4 text-xs">
        <div class="font-semibold text-orange-700 mb-2">&#x1F4A1; Margin guide by pack size</div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
          <div class="bg-white rounded-lg p-2 text-center border border-orange-100"><div class="font-bold text-orange-600">60&#x2013;75%</div><div class="text-gray-500">1g &#x2013; 10g</div></div>
          <div class="bg-white rounded-lg p-2 text-center border border-orange-100"><div class="font-bold text-green-600">45&#x2013;60%</div><div class="text-gray-500">20g &#x2013; 100g</div></div>
          <div class="bg-white rounded-lg p-2 text-center border border-orange-100"><div class="font-bold text-blue-600">30&#x2013;45%</div><div class="text-gray-500">250g &#x2013; 1kg</div></div>
          <div class="bg-white rounded-lg p-2 text-center border border-orange-100"><div class="font-bold text-gray-600">15&#x2013;28%</div><div class="text-gray-500">5kg+</div></div>
        </div>
        <p class="text-gray-400 mt-2">Margin = profit &divide; selling price &times; 100. Higher margin for small packs covers packaging &amp; handling costs.</p>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 text-gray-400 text-xs uppercase tracking-wide">
              <th class="text-left px-3 py-2 font-medium w-28">Size</th>
              <th class="text-left px-3 py-2 font-medium w-20">Unit</th>
              <th class="text-right px-3 py-2 font-medium">Cost</th>
              <th class="text-right px-3 py-2 font-medium w-32">Margin %</th>
              <th class="text-right px-3 py-2 font-medium">Suggested Price</th>
              <th class="text-right px-3 py-2 font-medium">Your Price</th>
              <th class="text-right px-3 py-2 font-medium">Profit</th>
              <th class="px-2 py-2 w-8 no-print"></th>
            </tr>
          </thead>
          <tbody id="packTbody"></tbody>
        </table>
      </div>

      <div id="packSummary" class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3"></div>

      <div class="mt-5 bg-gray-50 rounded-xl p-4">
        <h4 class="font-semibold text-gray-700 text-sm mb-3">Price per gram across pack sizes</h4>
        <canvas id="packChart" height="160"></canvas>
      </div>
    </div>

    <!-- TAB 3: BLEND / MIX -->
    <div id="content-blend" class="tab-content hidden p-5">
      <div class="flex items-start justify-between flex-wrap gap-3 mb-4">
        <div>
          <h3 class="font-semibold text-gray-800 text-sm mb-1">Blend / Mix Calculator</h3>
          <p class="text-xs text-gray-400">Add each ingredient with its quantity and cost to calculate total blend cost and the recommended selling price for the finished mix packet.</p>
        </div>
        <div class="no-print flex gap-2">
          <button onclick="addBlendRow()" class="text-xs bg-green-50 text-green-700 border border-green-200 rounded-lg px-3 py-1.5 hover:bg-green-100">+ Add Ingredient</button>
          <button onclick="clearBlend()" class="text-xs text-gray-400 hover:text-gray-600">Clear all</button>
        </div>
      </div>

      <div class="flex gap-3 mb-4 flex-wrap items-end">
        <div class="w-full sm:w-64">
          <label class="block text-xs font-medium text-gray-500 mb-1">&#x1F4D6; Load Saved Recipe</label>
          <div class="flex gap-2">
            <select id="recipeSelect" class="flex-1 min-w-0 px-2 py-2 border border-green-200 bg-green-50 rounded-lg text-sm">
              <option value="">— choose recipe —</option>
            </select>
            <button onclick="loadRecipe()" class="shrink-0 whitespace-nowrap text-xs font-medium bg-green-600 text-white rounded-lg px-4 py-2 hover:bg-green-700">Load</button>
          </div>
        </div>
        <div class="flex-1 min-w-48">
          <label class="block text-xs font-medium text-gray-500 mb-1">Blend / Mix Name</label>
          <input id="blendName" type="text" placeholder="e.g. Garam Masala, Dry Fruit Mix&#x2026;"
            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" />
        </div>
        <div class="w-36">
          <label class="block text-xs font-medium text-gray-500 mb-1">Final Pack Size (g)</label>
          <input id="blendPackSize" type="number" value="100" min="1" step="1"
            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm text-right num" oninput="updateBlendTotals()" />
        </div>
        <div class="w-36">
          <label class="block text-xs font-medium text-gray-500 mb-1">Target Margin %</label>
          <input id="blendMargin" type="number" value="50" min="1" max="99" step="1"
            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm text-right num" oninput="updateBlendTotals()" />
        </div>
      </div>

      <!-- Value-added / processing cost -->
      <div class="bg-amber-50 border border-amber-100 rounded-xl p-3 mb-4">
        <div class="text-xs font-semibold text-amber-700 mb-2">&#x1F3ED; Value-added cost
          <span class="font-normal text-amber-500">&#x2014; रू per kg of finished blend, added on top of ingredient cost</span></div>
        <div class="grid grid-cols-3 gap-3">
          <div>
            <label class="block text-xs text-gray-500 mb-1">Labour रू/kg</label>
            <input id="blendLabor" type="number" value="50" min="0" step="5"
              class="w-full px-3 py-2 border border-amber-200 rounded-lg text-sm text-right num" oninput="updateBlendTotals()" />
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">Grinding रू/kg</label>
            <input id="blendGrinding" type="number" value="50" min="0" step="5"
              class="w-full px-3 py-2 border border-amber-200 rounded-lg text-sm text-right num" oninput="updateBlendTotals()" />
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">Transport रू/kg</label>
            <input id="blendTransport" type="number" value="50" min="0" step="5"
              class="w-full px-3 py-2 border border-amber-200 rounded-lg text-sm text-right num" oninput="updateBlendTotals()" />
          </div>
        </div>
      </div>

      <p class="text-xs text-gray-400 mb-3 -mt-1">&#x1F4A1; Type in the Ingredient column to search your POS products &#x2014; picking one auto-fills its cost per 100g from the latest purchase cost.</p>

      <div class="overflow-x-auto mb-4">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 text-gray-400 text-xs uppercase tracking-wide">
              <th class="text-left px-3 py-2 font-medium">Ingredient</th>
              <th class="text-right px-3 py-2 font-medium w-28">Quantity (g)</th>
              <th class="text-right px-3 py-2 font-medium w-36">Cost per 100g</th>
              <th class="text-right px-3 py-2 font-medium w-28">Ingredient Cost</th>
              <th class="text-right px-3 py-2 font-medium w-20">% of Mix</th>
              <th class="px-2 py-2 w-8 no-print"></th>
            </tr>
          </thead>
          <tbody id="blendTbody"></tbody>
          <tfoot id="blendFoot"></tfoot>
        </table>
      </div>

      <div id="blendSuggest" class="hidden mb-4">
        <div class="text-xs font-semibold text-green-700 mb-2">&#x1F33F; Goes well with &#x2014; click to add:</div>
        <div id="blendSuggestChips" class="flex flex-wrap gap-2"></div>
      </div>

      <div id="blendResults" class="hidden grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4"></div>

      <div id="blendBreakdown" class="hidden">
        <h4 class="font-semibold text-gray-700 text-sm mb-3">Suggested prices for this blend at different pack sizes</h4>
        <div id="blendBreakdownTable" class="overflow-x-auto"></div>
      </div>
    </div>

    <!-- TAB 4: VOLUME -->
    <div id="content-vol" class="tab-content hidden p-5">
      <p class="text-xs text-gray-400 mb-4">Calculate total revenue and profit for bulk orders.</p>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1">Channel</label>
          <select id="volChannel" onchange="renderVol()" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
            <option value="ws">Wholesale</option>
            <option value="vs">Store Price</option>
            <option value="mrp">Retail / MRP</option>
            <option value="prem">Online / Premium</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1">Number of Units</label>
          <input id="volUnits" type="number" value="100" min="1"
            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm num" oninput="renderVol()" />
        </div>
        <div id="volSummaryBox" class="flex flex-col justify-end text-sm text-gray-500">
          &#x2014; enter cost price above &#x2014;
        </div>
      </div>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5" id="volCards"></div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 text-gray-400 text-xs uppercase tracking-wide">
              <th class="text-left px-4 py-2 font-medium">Units</th>
              <th class="text-right px-4 py-2 font-medium">WS Revenue</th>
              <th class="text-right px-4 py-2 font-medium">Store Revenue</th>
              <th class="text-right px-4 py-2 font-medium">MRP Revenue</th>
              <th class="text-right px-4 py-2 font-medium">WS Profit</th>
              <th class="text-right px-4 py-2 font-medium">Store Profit</th>
              <th class="text-right px-4 py-2 font-medium">MRP Profit</th>
            </tr>
          </thead>
          <tbody id="volTbody"><tr><td colspan="7" class="text-center text-gray-300 py-8">Enter cost price above</td></tr></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<footer class="text-center text-xs text-gray-400 py-5 no-print">
  RishiPath Pricing Tool &#x2014; for internal planning use only.
</footer>

<script>
// ─────────────────────────────
//  CONSTANTS
// ─────────────────────────────
const SYM = { NPR: 'रू', INR: '₹', USD: '$' };

const DEFAULT_PACKS = [
  { size: 1,   unit: 'g',  margin: 75, yourPrice: null },
  { size: 5,   unit: 'g',  margin: 70, yourPrice: null },
  { size: 10,  unit: 'g',  margin: 65, yourPrice: null },
  { size: 20,  unit: 'g',  margin: 52, yourPrice: null },
  { size: 50,  unit: 'g',  margin: 52, yourPrice: null },
  { size: 100, unit: 'g',  margin: 45, yourPrice: null },
  { size: 250, unit: 'g',  margin: 38, yourPrice: null },
  { size: 500, unit: 'g',  margin: 30, yourPrice: null },
  { size: 1,   unit: 'kg', margin: 25, yourPrice: null },
  { size: 5,   unit: 'kg', margin: 18, yourPrice: null },
  { size: 25,  unit: 'kg', margin: 12, yourPrice: null },
];

const UNIT_G = {
  g:1, gm:1, gms:1, gram:1, grams:1,
  kg:1000, kgs:1000,
  ml:1, l:1000, ltr:1000, litre:1000,
  pcs:1, nos:1, capsules:1, caps:1, tablets:1
};

// ─────────────────────────────
//  STATE
// ─────────────────────────────
let packRows   = JSON.parse(JSON.stringify(DEFAULT_PACKS));
let blendRows  = [{ name:'', qty:0, costPer100:0, product_id:null }, { name:'', qty:0, costPer100:0, product_id:null }];
let packChart  = null;
let selProduct = null;
let selVariant = null;
let searchTmr  = null;
let recipes    = [];
let blendSearchTmr  = null;
let blendSuggestTmr = null;
let blendResults    = {};   // row index -> last fetched products

// ─────────────────────────────
//  HELPERS
// ─────────────────────────────
function sym()  { return SYM[document.getElementById('currency').value] || 'रू'; }
function cp()   { return parseFloat(document.getElementById('costPrice').value) || 0; }
function fmt(v, d) {
  d = d === undefined ? 2 : d;
  if (v === null || v === undefined || isNaN(v)) return '—';
  var abs = Math.abs(v).toLocaleString('en-IN', { minimumFractionDigits: d, maximumFractionDigits: d });
  return sym() + ' ' + (v < 0 ? '-' : '') + abs;
}
function n(v, d) {
  d = d === undefined ? 2 : d;
  return isNaN(v) ? '0' : parseFloat(v).toFixed(d);
}
function gm(price, cost) { return price > 0 ? (price - cost) / price * 100 : 0; }
function toG(size, unit) { return size * (UNIT_G[(unit||'g').toLowerCase()] || 1); }
function marginBadge(p) {
  var cls = p >= 45 ? 'margin-great' : p >= 25 ? 'margin-ok' : 'margin-low';
  return '<span class="tier-badge ' + cls + '">' + n(p) + '%</span>';
}
function prices() {
  var c = cp();
  return {
    c: c,
    ws:   c * (1 + (parseFloat(document.getElementById('wsM').value)   || 13) / 100),
    vs:   c * (1 + (parseFloat(document.getElementById('vsM').value)   || 20) / 100),
    mrp:  c * (1 + (parseFloat(document.getElementById('mrpM').value)  || 30) / 100),
    prem: c * (1 + (parseFloat(document.getElementById('premM').value) || 50) / 100),
  };
}

// ─────────────────────────────
//  PRODUCT SEARCH
// ─────────────────────────────
function onSearch() {
  clearTimeout(searchTmr);
  var q = document.getElementById('productSearch').value.trim();
  if (q.length < 1) { hideDropdown(); return; }
  searchTmr = setTimeout(function() { fetchProducts(q); }, 280);
}
function onSearchFocus() {
  var q = document.getElementById('productSearch').value.trim();
  if (q.length >= 1) fetchProducts(q);
}
function hideDropdown() {
  document.getElementById('productSearchResults').classList.add('hidden');
}
async function fetchProducts(q) {
  var el = document.getElementById('productSearchResults');
  el.innerHTML = '<div class="s-item text-xs text-gray-400">Searching&#x2026;</div>';
  el.classList.remove('hidden');
  try {
    var res  = await fetch('/api/price-calculator/products?q=' + encodeURIComponent(q), {
      credentials: 'same-origin', headers: { Accept: 'application/json' }
    });
    var data = await res.json().catch(function() { return null; });
    if (!data) { el.innerHTML = '<div class="s-item text-xs text-red-400">&#x26A0;&#xFE0F; Server error</div>'; return; }
    if (data.auth_required) {
      el.innerHTML = '<div class="s-item text-xs text-amber-700">&#x1F512; <a href="/admin/login" target="_blank" class="underline">Log in</a> to search POS products</div>';
      return;
    }
    var products = data.products || [];
    if (!products.length) { el.innerHTML = '<div class="s-item text-xs text-gray-400">No products found</div>'; return; }
    el.innerHTML = products.map(function(p) {
      var vl = p.variants.map(function(v) { return v.pack_size + v.unit.toUpperCase(); }).join(', ');
      return '<div class="s-item" onclick=\'pickProduct(' + JSON.stringify(p).replace(/\'/g, "&#39;") + ')\'>' +
        '<div class="font-semibold text-gray-800 text-sm">' + p.name + '</div>' +
        '<div class="text-xs text-gray-400 mt-0.5">' + (p.sku ? p.sku + ' · ' : '') + p.variants.length + ' variant(s): ' + (vl || '—') + '</div>' +
        '</div>';
    }).join('');
  } catch(e) {
    el.innerHTML = '<div class="s-item text-xs text-red-400">&#x26A0;&#xFE0F; Network error &#x2014; please try again</div>';
  }
}
function pickProduct(p) {
  selProduct = p;
  document.getElementById('productSearch').value = p.name;
  hideDropdown();
  renderVariants(p.variants);
  document.getElementById('variantWrap').classList.remove('hidden');
  if (p.variants.length === 1) pickVariant(p.variants[0]);
}
function renderVariants(variants) {
  document.getElementById('variantPills').innerHTML = variants.map(function(v) {
    var active = selVariant && selVariant.id === v.id ? 'active' : '';
    return '<span class="vpill ' + active + '" onclick=\'pickVariant(' + JSON.stringify(v).replace(/\'/g, "&#39;") + ')\'>' +
      v.pack_size + v.unit.toUpperCase() + '</span>';
  }).join('');
}
function pickVariant(v) {
  selVariant = v;
  if (v.cost_price > 0) {
    document.getElementById('costPrice').value = v.cost_price;
    document.getElementById('cpNote').textContent = 'Auto-filled: cost for ' + v.pack_size + v.unit.toUpperCase();
  }
  document.getElementById('cpUnitLabel').textContent = '— per ' + v.pack_size + v.unit.toUpperCase();
  var parts = [selProduct && selProduct.name, v.pack_size + v.unit.toUpperCase()];
  if (v.cost_price) parts.push('Cost: ' + fmt(v.cost_price));
  if (v.mrp_india)  parts.push('MRP: '  + fmt(v.mrp_india));
  document.getElementById('selInfoText').textContent = parts.filter(Boolean).join(' · ');
  document.getElementById('selInfo').classList.remove('hidden');
  if (selProduct) renderVariants(selProduct.variants);
  refreshAll();
}
function clearProduct() {
  selProduct = null; selVariant = null;
  document.getElementById('productSearch').value = '';
  document.getElementById('variantWrap').classList.add('hidden');
  document.getElementById('selInfo').classList.add('hidden');
  document.getElementById('cpNote').textContent = '';
  document.getElementById('cpUnitLabel').textContent = '— per unit';
}
document.addEventListener('click', function(e) {
  if (!e.target.closest('#productSearch') && !e.target.closest('#productSearchResults')) hideDropdown();
});

// ─────────────────────────────
//  MAIN REFRESH
// ─────────────────────────────
function refreshAll() {
  document.querySelectorAll('.sym-s').forEach(function(el) { el.textContent = sym(); });
  document.getElementById('symMain').textContent = sym();
  renderQuickBar();
  var activeContent = document.querySelector('.tab-content:not(.hidden)');
  var t = activeContent ? activeContent.id.replace('content-','') : 'tiers';
  renderTab(t);
}
function renderTab(t) {
  if (t === 'tiers') renderTiers();
  if (t === 'packs') renderPacks();
  if (t === 'blend') calcBlend();
  if (t === 'vol')   renderVol();
}

// ─────────────────────────────
//  QUICK BAR
// ─────────────────────────────
function renderQuickBar() {
  var p = prices();
  var items = [
    { l: 'Your Cost',    v: p.c,    color: 'gray'   },
    { l: 'Wholesale',    v: p.ws,   color: 'blue'   },
    { l: 'Store Price',  v: p.vs,   color: 'green'  },
    { l: 'Retail / MRP', v: p.mrp,  color: 'orange' },
  ];
  document.getElementById('quickBar').innerHTML = items.map(function(it) {
    return '<div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3">' +
      '<div class="text-xs font-medium text-' + it.color + '-500">' + it.l + '</div>' +
      '<div class="text-xl font-bold text-' + it.color + '-700 num">' + (it.v > 0 ? fmt(it.v) : '—') + '</div>' +
      '</div>';
  }).join('');
}

// ─────────────────────────────
//  TAB 1: PRICING TIERS
// ─────────────────────────────
function renderTiers() {
  var p = prices();
  var container = document.getElementById('tiersTable');
  if (p.c <= 0) {
    container.innerHTML = '<p class="text-gray-300 text-sm text-center py-8">Enter cost price above</p>';
    return;
  }
  var rows = [
    { label: 'Wholesale',      price: p.ws,   color: 'blue',   note: 'Distributors &amp; agents' },
    { label: 'Store Price',    price: p.vs,   color: 'green',  note: 'Direct retail stores' },
    { label: 'Retail / MRP',   price: p.mrp,  color: 'orange', note: 'End consumer price' },
    { label: 'Online/Premium', price: p.prem, color: 'purple', note: 'Online / gift packs' },
  ];
  container.innerHTML = '<table class="w-full text-sm">' +
    '<thead><tr class="bg-gray-50 text-gray-400 text-xs uppercase tracking-wide">' +
    '<th class="text-left px-4 py-2 font-medium">Channel</th>' +
    '<th class="text-right px-4 py-2 font-medium">Price</th>' +
    '<th class="text-right px-4 py-2 font-medium">Markup</th>' +
    '<th class="text-right px-4 py-2 font-medium">Your Profit</th>' +
    '<th class="text-right px-4 py-2 font-medium">Gross Margin</th>' +
    '<th class="text-right px-4 py-2 font-medium hidden sm:table-cell">Best For</th>' +
    '</tr></thead><tbody>' +
    rows.map(function(r) {
      var profit = r.price - p.c;
      var mkp    = p.c > 0 ? profit / p.c * 100 : 0;
      var gmPct  = gm(r.price, p.c);
      return '<tr class="border-b border-gray-50 hover:bg-' + r.color + '-50">' +
        '<td class="px-4 py-3 font-medium text-' + r.color + '-700">' + r.label + '</td>' +
        '<td class="px-4 py-3 text-right font-bold text-' + r.color + '-700 num">' + fmt(r.price) + '</td>' +
        '<td class="px-4 py-3 text-right text-gray-600">+' + n(mkp) + '%</td>' +
        '<td class="px-4 py-3 text-right font-semibold text-green-600 num">' + fmt(profit) + '</td>' +
        '<td class="px-4 py-3 text-right">' + marginBadge(gmPct) + '</td>' +
        '<td class="px-4 py-3 text-right text-xs text-gray-400 hidden sm:table-cell">' + r.note + '</td>' +
        '</tr>';
    }).join('') +
    '</tbody></table>' +
    '<div class="mt-3 flex gap-4 text-xs text-gray-400 flex-wrap">' +
    '<span><span class="text-green-600 font-bold">●</span> ≥ 45% — healthy</span>' +
    '<span><span class="text-amber-500 font-bold">●</span> 25–44% — acceptable</span>' +
    '<span><span class="text-red-500 font-bold">●</span> &lt; 25% — thin</span>' +
    '</div>';
}

// ─────────────────────────────
//  TAB 2: PACK PRICING
// ─────────────────────────────
function costPerGram() {
  var costVal = cp();
  if (costVal <= 0) return 0;
  if (selVariant && selVariant.pack_size > 0) {
    return costVal / toG(selVariant.pack_size, selVariant.unit);
  }
  return costVal / 100;
}

function renderPacks() {
  var cpg = costPerGram();
  if (cp() > 0) {
    var note = selVariant
      ? fmt(cp()) + ' for ' + selVariant.pack_size + selVariant.unit.toUpperCase() + ' = ' + fmt(cpg * 100, 3) + ' per 100g'
      : fmt(cp()) + ' assumed per 100g — select a variant above for exact calculation';
    document.getElementById('packCostNoteText').textContent = note;
    document.getElementById('packCostNote').classList.remove('hidden');
  } else {
    document.getElementById('packCostNote').classList.add('hidden');
  }

  var tbody = document.getElementById('packTbody');
  if (!packRows.length) {
    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-gray-300 py-6">No rows. Click "+ Add Size".</td></tr>';
    return;
  }

  tbody.innerHTML = packRows.map(function(row, i) {
    var grams   = toG(row.size, row.unit);
    var rawCost = cpg > 0 ? cpg * grams : 0;
    var suggest = rawCost > 0 && row.margin < 100 ? rawCost / (1 - row.margin / 100) : 0;
    var yourP   = row.yourPrice !== null && row.yourPrice !== '' ? parseFloat(row.yourPrice) : suggest;
    var aGM     = rawCost > 0 && yourP > 0 ? gm(yourP, rawCost) : 0;
    var profit  = yourP - rawCost;
    var isBase  = selVariant && selVariant.pack_size == row.size && selVariant.unit.toLowerCase() === row.unit.toLowerCase();
    var unitOpts = ['g','kg','ml','l','pcs'].map(function(u) {
      return '<option' + (u === row.unit ? ' selected' : '') + '>' + u + '</option>';
    }).join('');
    return '<tr class="' + (isBase ? 'pack-row-highlight' : 'hover:bg-gray-50') + ' border-b border-gray-100 text-sm">' +
      '<td class="px-3 py-2"><input type="number" value="' + row.size + '" min="0.001" step="any" class="w-20 px-2 py-1 border border-gray-200 rounded text-xs text-center" oninput="packRows[' + i + '].size=parseFloat(this.value)||0; renderPacks();" /></td>' +
      '<td class="px-3 py-2"><select class="px-2 py-1 border border-gray-200 rounded text-xs" onchange="packRows[' + i + '].unit=this.value; renderPacks();">' + unitOpts + '</select></td>' +
      '<td class="px-3 py-2 text-right text-xs text-gray-600 num">' + (rawCost > 0 ? fmt(rawCost, 3) : '<span class="text-gray-300">Enter CP ↑</span>') + '</td>' +
      '<td class="px-3 py-2"><div class="flex items-center justify-end gap-1"><input type="number" value="' + row.margin + '" min="1" max="99" step="1" class="w-14 px-2 py-1 border border-gray-200 rounded text-xs text-center" oninput="packRows[' + i + '].margin=parseFloat(this.value)||0; renderPacks();" /><span class="text-xs text-gray-400">%</span></div></td>' +
      '<td class="px-3 py-2 text-right text-xs text-blue-600 num">' + (suggest > 0 ? fmt(suggest) : '—') + '</td>' +
      '<td class="px-3 py-2"><input type="number" value="' + (row.yourPrice !== null ? row.yourPrice : n(suggest)) + '" min="0" step="0.01" placeholder="' + n(suggest) + '" class="w-24 px-2 py-1 border border-orange-200 bg-orange-50 rounded text-xs text-right font-medium" oninput="packRows[' + i + '].yourPrice=parseFloat(this.value)||null; renderPacks();" /></td>' +
      '<td class="px-3 py-2 text-right text-xs font-semibold ' + (profit >= 0 ? 'text-green-600' : 'text-red-600') + ' num">' +
        (rawCost > 0 && yourP > 0 ? fmt(profit) + '<div class="font-normal">' + marginBadge(aGM) + '</div>' : '—') +
      '</td>' +
      '<td class="px-3 py-2 no-print text-center"><button onclick="packRows.splice(' + i + ',1); renderPacks();" class="text-gray-300 hover:text-red-400 text-xs">✕</button></td>' +
      '</tr>';
  }).join('');

  renderPackSummary(cpg);
  renderPackChart(cpg);
}

function renderPackSummary(cpg) {
  if (cpg <= 0) { document.getElementById('packSummary').innerHTML = ''; return; }
  var validRows = packRows.filter(function(r) { return r.size > 0; });
  var margins = validRows.map(function(row) {
    var rawCost = cpg * toG(row.size, row.unit);
    var suggest = rawCost > 0 && row.margin < 100 ? rawCost / (1 - row.margin / 100) : 0;
    var yourP   = row.yourPrice !== null ? parseFloat(row.yourPrice) : suggest;
    return rawCost > 0 && yourP > 0 ? gm(yourP, rawCost) : null;
  }).filter(function(m) { return m !== null; });
  if (!margins.length) { document.getElementById('packSummary').innerHTML = ''; return; }
  var avg = margins.reduce(function(a,b){return a+b;}, 0) / margins.length;
  var min = Math.min.apply(null, margins);
  var max = Math.max.apply(null, margins);
  var cards = [
    { l:'Pack Sizes', v:validRows.length,  sub:'configured',    c:'gray'   },
    { l:'Avg Margin', v:n(avg)+'%',        sub:'across sizes',  c:avg>=35?'green':'yellow' },
    { l:'Min Margin', v:n(min)+'%',        sub:'thinnest pack', c:min>=20?'blue':'red' },
    { l:'Max Margin', v:n(max)+'%',        sub:'premium pack',  c:'orange' },
  ];
  document.getElementById('packSummary').innerHTML = cards.map(function(c) {
    return '<div class="bg-' + c.c + '-50 rounded-xl border border-' + c.c + '-100 p-3 text-center">' +
      '<div class="text-xs text-' + c.c + '-400 font-medium mb-0.5">' + c.l + '</div>' +
      '<div class="text-lg font-bold text-' + c.c + '-700">' + c.v + '</div>' +
      '<div class="text-xs text-' + c.c + '-400">' + c.sub + '</div>' +
      '</div>';
  }).join('');
}

function renderPackChart(cpg) {
  var rows   = packRows.filter(function(r) { return r.size > 0; });
  var labels = rows.map(function(r) { return r.size + r.unit; });
  var ppg = rows.map(function(r) {
    var grams = toG(r.size, r.unit);
    var rawCost = cpg * grams;
    var suggest = rawCost > 0 && r.margin < 100 ? rawCost / (1 - r.margin / 100) : 0;
    var yourP   = r.yourPrice !== null ? parseFloat(r.yourPrice) : suggest;
    return grams > 0 && yourP > 0 ? yourP / grams : 0;
  });
  var margs = rows.map(function(r) {
    var rawCost = cpg * toG(r.size, r.unit);
    var suggest = rawCost > 0 && r.margin < 100 ? rawCost / (1 - r.margin / 100) : 0;
    var yourP   = r.yourPrice !== null ? parseFloat(r.yourPrice) : suggest;
    return rawCost > 0 && yourP > 0 ? gm(yourP, rawCost) : 0;
  });
  if (packChart) packChart.destroy();
  var ctx = document.getElementById('packChart').getContext('2d');
  packChart = new Chart(ctx, {
    data: {
      labels: labels,
      datasets: [
        { type:'bar',  label:'Price/g',  data:ppg,   backgroundColor:'#fb923c', borderRadius:4, yAxisID:'y'  },
        { type:'line', label:'Margin %', data:margs, borderColor:'#16a34a', borderWidth:2, pointRadius:4, tension:.3, fill:false, yAxisID:'y2' },
      ]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { position:'top' },
        tooltip: { callbacks: { label: function(c) {
          return c.dataset.label + ': ' + (c.datasetIndex === 0 ? sym() + n(c.raw,4) + '/g' : n(c.raw) + '%');
        }}}
      },
      scales: {
        y:  { title:{ display:true, text:'Price per gram' }, ticks:{ callback: function(v) { return sym()+n(v,3); } } },
        y2: { position:'right', title:{ display:true, text:'Margin %' }, ticks:{ callback: function(v) { return v+'%'; } }, grid:{ drawOnChartArea:false } }
      }
    }
  });
}

function addPackRow()    { packRows.push({ size:1, unit:'g', margin:50, yourPrice:null }); renderPacks(); }
function resetPackRows() { packRows = JSON.parse(JSON.stringify(DEFAULT_PACKS)); renderPacks(); }

// ─────────────────────────────
//  TAB 3: BLEND CALCULATOR
// ─────────────────────────────
function addBlendRow(preset) {
  blendRows.push(preset || { name:'', qty:0, costPer100:0, product_id:null });
  calcBlend();
}
function clearBlend() {
  blendRows = [{ name:'', qty:0, costPer100:0, product_id:null }, { name:'', qty:0, costPer100:0, product_id:null }];
  calcBlend();
}

// Full re-render (row add/remove, recipe load, tab switch) …
function calcBlend() {
  renderBlendRows();
  updateBlendTotals();
}

function renderBlendRows() {
  var tbody = document.getElementById('blendTbody');
  tbody.innerHTML = blendRows.map(function(row, i) {
    return '<tr class="blend-row border-b border-gray-100 hover:bg-gray-50">' +
      '<td class="relative">' +
        '<input id="bName-' + i + '" type="text" value="' + (row.name||'').replace(/"/g,'&quot;') + '" placeholder="Search product… e.g. Cloves, Cumin" autocomplete="off" class="w-full px-2 py-1 border ' + (row.product_id ? 'border-green-300 bg-green-50' : 'border-gray-200') + ' rounded text-xs" oninput="blendRows[' + i + '].name=this.value; blendRows[' + i + '].product_id=null; onBlendSearch(' + i + ');" onfocus="onBlendSearch(' + i + ')" />' +
        '<div id="bDrop-' + i + '" class="b-drop hidden"></div>' +
      '</td>' +
      '<td><input type="number" value="' + (row.qty||'') + '" min="0" step="0.1" placeholder="g" class="w-full px-2 py-1 border border-gray-200 rounded text-xs text-right num" oninput="blendRows[' + i + '].qty=parseFloat(this.value)||0; updateBlendTotals();" /></td>' +
      '<td><div class="flex items-center gap-1"><span class="text-gray-400 text-xs sym-s">' + sym() + '</span><input type="number" value="' + (row.costPer100||'') + '" min="0" step="0.01" placeholder="cost/100g" class="flex-1 px-2 py-1 border border-gray-200 rounded text-xs text-right num" oninput="blendRows[' + i + '].costPer100=parseFloat(this.value)||0; updateBlendTotals();" /></div></td>' +
      '<td id="bCost-' + i + '" class="text-right text-xs num text-gray-300">—</td>' +
      '<td id="bPct-' + i + '" class="text-right text-xs text-gray-500">—</td>' +
      '<td class="no-print text-center"><button onclick="blendRows.splice(' + i + ',1); calcBlend();" class="text-gray-300 hover:text-red-400 text-xs">✕</button></td>' +
      '</tr>';
  }).join('');
}

// Per-row product search
function onBlendSearch(i) {
  clearTimeout(blendSearchTmr);
  var q = (blendRows[i].name || '').trim();
  var drop = document.getElementById('bDrop-' + i);
  if (!drop) return;
  if (q.length < 1 || blendRows[i].product_id) { drop.classList.add('hidden'); return; }
  blendSearchTmr = setTimeout(async function() {
    drop.innerHTML = '<div class="s-item text-xs text-gray-400">Searching…</div>';
    drop.classList.remove('hidden');
    try {
      var res  = await fetch('/api/price-calculator/products?q=' + encodeURIComponent(q), { credentials:'same-origin', headers:{ Accept:'application/json' } });
      var data = await res.json().catch(function(){ return null; });
      if (!data || data.auth_required) {
        drop.innerHTML = '<div class="s-item text-xs text-amber-700">&#x1F512; <a href="/admin/login" target="_blank" class="underline">Log in</a> to search POS products</div>';
        return;
      }
      var products = data.products || [];
      blendResults[i] = products;
      if (!products.length) { drop.innerHTML = '<div class="s-item text-xs text-gray-400">No products found — you can still type a name and cost manually</div>'; return; }
      drop.innerHTML = products.map(function(p, j) {
        var cph = productCostPer100(p);
        return '<div class="s-item" onclick="pickBlendIngredient(' + i + ',' + j + ')">' +
          '<div class="font-semibold text-gray-800 text-xs">' + p.name + '</div>' +
          '<div class="text-[11px] text-gray-400">' + (cph !== null ? 'Cost: ' + fmt(cph) + ' / 100g' : 'no cost on file') + '</div>' +
          '</div>';
      }).join('');
    } catch(e) {
      drop.innerHTML = '<div class="s-item text-xs text-red-400">&#x26A0;&#xFE0F; Network error</div>';
    }
  }, 280);
}

// Cheapest-per-gram costed variant (largest pack) → cost per 100g
function productCostPer100(p) {
  var best = null;
  (p.variants || []).forEach(function(v) {
    if (!(v.cost_price > 0)) return;
    var grams = toG(v.pack_size, v.unit);
    if (grams <= 0) return;
    if (!best || grams > best.grams) best = { grams: grams, cost: v.cost_price };
  });
  return best ? best.cost / best.grams * 100 : null;
}

function pickBlendIngredient(i, j) {
  var p = (blendResults[i] || [])[j];
  if (!p) return;
  var cph = productCostPer100(p);
  blendRows[i].name = p.name;
  blendRows[i].product_id = p.id;
  if (cph !== null) blendRows[i].costPer100 = Math.round(cph * 100) / 100;
  calcBlend();
}

document.addEventListener('click', function(e) {
  if (!e.target.closest('.b-drop') && !e.target.closest('[id^="bName-"]')) {
    document.querySelectorAll('.b-drop').forEach(function(el) { el.classList.add('hidden'); });
  }
});

// Saved recipes (product compositions from the POS)
async function fetchRecipes() {
  try {
    var res  = await fetch('/api/price-calculator/recipes', { credentials:'same-origin', headers:{ Accept:'application/json' } });
    var data = await res.json().catch(function(){ return null; });
    recipes  = (data && data.recipes) || [];
    var sel  = document.getElementById('recipeSelect');
    sel.innerHTML = '<option value="">— choose recipe —</option>' + recipes.map(function(r, i) {
      return '<option value="' + i + '">' + r.name + (r.name_nepali ? ' (' + r.name_nepali + ')' : '') + '</option>';
    }).join('');
  } catch(e) { /* stay silent — manual entry still works */ }
}

function loadRecipe() {
  var idx = document.getElementById('recipeSelect').value;
  if (idx === '') return;
  var r = recipes[parseInt(idx)];
  if (!r) return;
  document.getElementById('blendName').value = r.name;
  blendRows = r.components.map(function(c) {
    return { name: c.name, qty: c.qty, costPer100: c.cost_per_100g || 0, product_id: c.product_id };
  });
  calcBlend();
}

// KB pairing suggestions for the current mix
function updateBlendSuggestions() {
  clearTimeout(blendSuggestTmr);
  var ids = blendRows.map(function(r) { return r.product_id; }).filter(Boolean);
  var box = document.getElementById('blendSuggest');
  if (!ids.length) { box.classList.add('hidden'); return; }
  blendSuggestTmr = setTimeout(async function() {
    try {
      var res  = await fetch('/api/price-calculator/suggestions?products=' + ids.join(','), { credentials:'same-origin', headers:{ Accept:'application/json' } });
      var data = await res.json().catch(function(){ return null; });
      var sugg = (data && data.suggestions) || [];
      if (!sugg.length) { box.classList.add('hidden'); return; }
      window._blendSugg = sugg;
      document.getElementById('blendSuggestChips').innerHTML = sugg.map(function(s, i) {
        return '<span class="chip" title="' + (s.reason || '') + '" onclick="addSuggestedIngredient(' + i + ')">+ ' + s.name +
          (s.cost_per_100g ? ' <span class="text-green-500">' + fmt(s.cost_per_100g, 0) + '/100g</span>' : '') + '</span>';
      }).join('');
      box.classList.remove('hidden');
    } catch(e) { box.classList.add('hidden'); }
  }, 500);
}

function addSuggestedIngredient(i) {
  var s = (window._blendSugg || [])[i];
  if (!s) return;
  // drop trailing empty rows so the suggestion lands next to real ones
  blendRows = blendRows.filter(function(r) { return (r.name||'').trim() !== '' || r.qty > 0; });
  addBlendRow({ name: s.name, qty: 0, costPer100: s.cost_per_100g || 0, product_id: s.product_id });
}

// Totals only — never rebuilds the rows, so typing keeps focus
function updateBlendTotals() {
  var totalQty = blendRows.reduce(function(s,r) { return s + (parseFloat(r.qty)||0); }, 0);

  blendRows.forEach(function(row, i) {
    var qty  = parseFloat(row.qty) || 0;
    var cph  = parseFloat(row.costPer100) || 0;
    var cost = qty * cph / 100;
    var pct  = totalQty > 0 ? qty / totalQty * 100 : 0;
    var cEl  = document.getElementById('bCost-' + i);
    var pEl  = document.getElementById('bPct-' + i);
    if (cEl) { cEl.textContent = cost > 0 ? fmt(cost) : '—'; cEl.className = 'text-right text-xs num ' + (cost>0?'text-gray-700 font-medium':'text-gray-300'); }
    if (pEl) { pEl.textContent = qty > 0 ? n(pct,1) + '%' : '—'; }
  });

  updateBlendSuggestions();

  var totalCost   = blendRows.reduce(function(s,r) { return s + ((parseFloat(r.qty)||0) * (parseFloat(r.costPer100)||0) / 100); }, 0);
  var cpg         = totalQty > 0 && totalCost > 0 ? totalCost / totalQty : 0;   // raw material cost per gram
  var blendMargin = parseFloat(document.getElementById('blendMargin').value) || 50;
  var packSize    = parseFloat(document.getElementById('blendPackSize').value) || 100;

  // Value-added processing (labour + grinding + transport) — रू per kg of blend
  var procPerKg   = (parseFloat(document.getElementById('blendLabor').value)||0)
                  + (parseFloat(document.getElementById('blendGrinding').value)||0)
                  + (parseFloat(document.getElementById('blendTransport').value)||0);
  var procCpg     = procPerKg / 1000;              // processing cost per gram
  var loadedCpg   = cpg > 0 ? cpg + procCpg : 0;   // fully-loaded cost per gram

  var packCost    = loadedCpg * packSize;          // material + processing for the pack
  var blendPrice  = packCost > 0 && blendMargin < 100 ? packCost / (1 - blendMargin / 100) : 0;

  document.getElementById('blendFoot').innerHTML = totalQty > 0
    ? '<tr class="border-t-2 border-gray-200 bg-gray-50 font-semibold text-sm">' +
      '<td class="px-3 py-2">Total Blend</td>' +
      '<td class="px-3 py-2 text-right num">' + n(totalQty) + 'g</td>' +
      '<td class="px-3 py-2 text-right num text-xs text-gray-500">' + fmt(cpg*100,2) + '/100g</td>' +
      '<td class="px-3 py-2 text-right num">' + (totalCost>0?fmt(totalCost):'—') + '</td>' +
      '<td class="px-3 py-2 text-right">100%</td><td></td></tr>'
    : '';

  var res = document.getElementById('blendResults');
  if (totalCost > 0 && totalQty > 0) {
    res.classList.remove('hidden');
    var cards2 = [
      { l:'Material Cost',              v:fmt(totalCost),           s:'for '+n(totalQty)+'g',                    c:'gray'  },
      { l:'Loaded Cost / 100g',        v:fmt(loadedCpg*100),       s:'+ रू '+n(procPerKg,0)+'/kg processing',   c:'blue'  },
      { l:'Cost for '+packSize+'g pack', v:fmt(packCost),          s:'material + processing',                   c:'amber' },
      { l:'Suggested Sell Price',       v:blendPrice>0?fmt(blendPrice):'—', s:'at '+blendMargin+'% margin',      c:'green' },
    ];
    res.innerHTML = cards2.map(function(c) {
      return '<div class="bg-' + c.c + '-50 rounded-xl border border-' + c.c + '-100 p-3 text-center">' +
        '<div class="text-xs text-' + c.c + '-500 font-medium mb-0.5">' + c.l + '</div>' +
        '<div class="text-lg font-bold text-' + c.c + '-700 num">' + c.v + '</div>' +
        '<div class="text-xs text-' + c.c + '-400">' + c.s + '</div></div>';
    }).join('');
  } else {
    res.classList.add('hidden');
  }

  var bd = document.getElementById('blendBreakdown');
  if (loadedCpg > 0) {
    bd.classList.remove('hidden');
    var bps = [10, 20, 25, 50, 100, 200, 250, 500];
    document.getElementById('blendBreakdownTable').innerHTML =
      '<table class="w-full text-sm">' +
      '<thead><tr class="bg-gray-50 text-gray-400 text-xs uppercase tracking-wide">' +
      '<th class="text-left px-3 py-2 font-medium">Pack Size</th>' +
      '<th class="text-right px-3 py-2 font-medium">Loaded Cost</th>' +
      '<th class="text-right px-3 py-2 font-medium">Price @ 50%</th>' +
      '<th class="text-right px-3 py-2 font-medium">Price @ 40%</th>' +
      '<th class="text-right px-3 py-2 font-medium">Price @ 30%</th>' +
      '<th class="text-right px-3 py-2 font-medium">Profit @ 50%</th>' +
      '</tr></thead><tbody>' +
      bps.map(function(ps) {
        var cost = loadedCpg * ps;
        var p50  = cost / 0.50;
        var p40  = cost / 0.60;
        var p30  = cost / 0.70;
        var isB  = ps === packSize;
        return '<tr class="' + (isB?'bg-green-50 font-semibold':'hover:bg-gray-50') + ' border-b border-gray-100">' +
          '<td class="px-3 py-2">' + ps + 'g' + (isB?' ← selected pack':'') + '</td>' +
          '<td class="px-3 py-2 text-right num">' + fmt(cost) + '</td>' +
          '<td class="px-3 py-2 text-right num text-green-700">' + fmt(p50) + '</td>' +
          '<td class="px-3 py-2 text-right num">' + fmt(p40) + '</td>' +
          '<td class="px-3 py-2 text-right num text-gray-500">' + fmt(p30) + '</td>' +
          '<td class="px-3 py-2 text-right num text-green-600">' + fmt(p50-cost) + '</td>' +
          '</tr>';
      }).join('') + '</tbody></table>';
  } else {
    bd.classList.add('hidden');
  }
}

// ─────────────────────────────
//  TAB 4: VOLUME
// ─────────────────────────────
function renderVol() {
  var p     = prices();
  var ch    = document.getElementById('volChannel').value;
  var units = parseInt(document.getElementById('volUnits').value) || 0;
  var priceMap = { ws:p.ws, vs:p.vs, mrp:p.mrp, prem:p.prem };
  var price = priceMap[ch] || 0;
  var prof  = price - p.c;
  var gmPct = gm(price, p.c);
  document.getElementById('volSummaryBox').innerHTML = p.c > 0
    ? '<span class="text-sm">Per unit: ' + fmt(price) + ' − ' + fmt(p.c) + ' = <strong class="text-green-600">' + fmt(prof) + '</strong></span>'
    : '— enter cost price above —';
  var cards3 = p.c > 0 ? [
    { l:'Total Revenue', v:fmt(price*units), c:'blue'   },
    { l:'Total Profit',  v:fmt(prof*units),  c:'green'  },
    { l:'Total Cost',    v:fmt(p.c*units),   c:'gray'   },
    { l:'Gross Margin',  v:n(gmPct)+'%',     c:'orange' },
  ] : [];
  document.getElementById('volCards').innerHTML = cards3.map(function(c) {
    return '<div class="bg-' + c.c + '-50 rounded-xl border border-' + c.c + '-100 p-4 text-center">' +
      '<div class="text-xs text-' + c.c + '-400 font-medium uppercase mb-1">' + c.l + '</div>' +
      '<div class="text-xl font-bold text-' + c.c + '-700 num">' + c.v + '</div></div>';
  }).join('');
  if (p.c <= 0) {
    document.getElementById('volTbody').innerHTML = '<tr><td colspan="7" class="text-center text-gray-300 py-8">Enter cost price above</td></tr>';
    return;
  }
  var qtys = [10, 25, 50, 100, 250, 500, 1000, 5000];
  document.getElementById('volTbody').innerHTML = qtys.map(function(q) {
    var hi = q === units ? 'bg-orange-50 font-semibold' : 'hover:bg-gray-50';
    return '<tr class="' + hi + ' border-b border-gray-50 text-xs">' +
      '<td class="px-4 py-2">' + q.toLocaleString() + '</td>' +
      '<td class="px-4 py-2 text-right num">' + fmt(p.ws*q) + '</td>' +
      '<td class="px-4 py-2 text-right num">' + fmt(p.vs*q) + '</td>' +
      '<td class="px-4 py-2 text-right num font-semibold text-orange-600">' + fmt(p.mrp*q) + '</td>' +
      '<td class="px-4 py-2 text-right num text-green-600">' + fmt((p.ws-p.c)*q) + '</td>' +
      '<td class="px-4 py-2 text-right num text-green-600">' + fmt((p.vs-p.c)*q) + '</td>' +
      '<td class="px-4 py-2 text-right num text-green-700 font-semibold">' + fmt((p.mrp-p.c)*q) + '</td>' +
      '</tr>';
  }).join('');
}

// ─────────────────────────────
//  TAB SWITCHING
// ─────────────────────────────
function switchTab(t) {
  document.querySelectorAll('.tab-content').forEach(function(el) { el.classList.add('hidden'); });
  document.querySelectorAll('.tab-btn').forEach(function(el) { el.classList.remove('tab-active'); });
  document.getElementById('content-' + t).classList.remove('hidden');
  document.getElementById('tab-' + t).classList.add('tab-active');
  renderTab(t);
}

// ─────────────────────────────
//  INIT
// ─────────────────────────────
renderPacks();
calcBlend();
refreshAll();
fetchRecipes();
</script>
</body>
</html>
