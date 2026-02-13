<div class="flex flex-wrap gap-1">
    @if($getRecord()->stock_available)
        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
            📦 Stock
        </span>
    @endif

    @if($getRecord()->good_display)
        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
            ✨ Display
        </span>
    @endif

    @if($getRecord()->clean_store)
        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
            🧹 Clean
        </span>
    @endif

    @if($getRecord()->staff_trained)
        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
            👥 Trained
        </span>
    @endif

    @if($getRecord()->has_refrigeration)
        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-cyan-100 text-cyan-800">
            ❄️ Fridge
        </span>
    @endif

    @if($getRecord()->payment_collected)
        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800">
            💰 Payment
        </span>
    @endif

    @if($getRecord()->has_competition)
        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800">
            ⚔️ Competition
        </span>
    @endif

    @if(!$getRecord()->stock_available && !$getRecord()->good_display && !$getRecord()->clean_store && !$getRecord()->staff_trained)
        <span class="text-xs text-gray-400 italic">No feedback</span>
    @endif
</div>
