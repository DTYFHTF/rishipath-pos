<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Barcode Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            @php
                $stats = $this->getBarcodeStats();
            @endphp
            
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <div class="text-sm text-gray-600 dark:text-gray-400">Total Variants</div>
                <div class="text-2xl font-bold">{{ $stats['total_variants'] }}</div>
            </div>
            
            <div class="bg-green-50 dark:bg-green-900/20 rounded-lg shadow p-4">
                <div class="text-sm text-green-600 dark:text-green-400">With Barcode</div>
                <div class="text-2xl font-bold text-green-700 dark:text-green-300">{{ $stats['with_barcode'] }}</div>
            </div>
            
            <div class="bg-orange-50 dark:bg-orange-900/20 rounded-lg shadow p-4">
                <div class="text-sm text-orange-600 dark:text-orange-400">Without Barcode</div>
                <div class="text-2xl font-bold text-orange-700 dark:text-orange-300">{{ $stats['without_barcode'] }}</div>
            </div>
            
            <div class="bg-primary-50 dark:bg-primary-900/20 rounded-lg shadow p-4">
                <div class="text-sm text-primary-600 dark:text-primary-400">Coverage</div>
                <div class="text-2xl font-bold text-primary-700 dark:text-primary-300">{{ $stats['percentage'] }}%</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Quick Actions</h3>
            <div class="flex flex-wrap gap-3">
                <button 
                    wire:click="generateAllBarcodes"
                    class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition"
                >
                    🔢 Generate All Missing Barcodes
                </button>
            </div>
        </div>

        <!-- Label Generation Form -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Generate Barcode Labels</h3>
            
            <form wire:submit="generateLabels">
                {{ $this->form }}
                
                <div class="mt-4 flex gap-3">
                    <button 
                        type="submit"
                        class="px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition"
                    >
                        🏷️ Generate Labels
                    </button>
                    
                    @if(!empty($generatedLabels))
                        <button 
                            type="button"
                            wire:click="clearLabels"
                            class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition"
                        >
                            Clear
                        </button>
                    @endif
                </div>
            </form>
        </div>

        <!-- Generated Labels Preview -->
        @if(!empty($generatedLabels))
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold">Generated Labels ({{ count($generatedLabels) }})</h3>
                    <button 
                        onclick="window.print()"
                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition"
                    >
                        🖨️ Print All Labels
                    </button>
                </div>
                
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 print-labels" id="printable-area">
                    @foreach($generatedLabels as $label)
                        <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-4 text-center space-y-2 label-item">
                            <!-- Barcode Image -->
                            <div class="bg-white p-2 rounded">
                                <img 
                                    src="data:image/png;base64,{{ $label['barcode_image'] }}" 
                                    alt="Barcode" 
                                    class="mx-auto"
                                    style="max-width: 100%; height: 50px;"
                                />
                            </div>
                            
                            <!-- Barcode Number -->
                            <div class="text-xs font-mono font-bold">{{ $label['barcode'] }}</div>
                            
                            <!-- Product Info -->
                            <div class="text-sm font-semibold">{{ Str::limit($label['product_name'], 30) }}</div>
                            <div class="text-xs text-gray-600 dark:text-gray-400">{{ $label['variant_name'] }}</div>
                            
                            @if($showPrice)
                                <div class="text-sm font-bold text-primary-600">₹{{ number_format($label['mrp'], 2) }}</div>
                            @endif
                            
                            @if($showSKU)
                                <div class="text-xs text-gray-500">SKU: {{ $label['sku'] }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <style>
        @media print {
            /* Hide everything except labels */
            body * {
                visibility: hidden;
            }
            
            #printable-area, #printable-area * {
                visibility: visible;
            }
            
            #printable-area {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            
            .label-item {
                page-break-inside: avoid;
                break-inside: avoid;
                border: 1px solid #000 !important;
            }
            
            /* Remove gaps in print */
            .print-labels {
                gap: 0 !important;
            }
        }

        @page {
            size: A4;
            margin: 10mm;
        }
    </style>
</x-filament-panels::page>
