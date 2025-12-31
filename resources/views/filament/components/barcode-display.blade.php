<div class="p-6 text-center space-y-4">
    <div class="bg-white p-6 rounded-lg inline-block">
        <img src="data:image/png;base64,{{ $barcodeImage }}" alt="Barcode" class="mx-auto" style="max-width: 400px;" />
    </div>
    
    <div class="space-y-2">
        <div class="text-2xl font-bold font-mono">{{ $record->barcode }}</div>
        <div class="text-lg font-semibold">{{ $record->product->name }}</div>
        <div class="text-gray-600">{{ $record->pack_size }} {{ $record->unit }}</div>
        <div class="text-sm text-gray-500">SKU: {{ $record->sku }}</div>
        <div class="text-lg font-bold text-primary-600">₹{{ number_format($record->mrp_india, 2) }}</div>
    </div>
    
    <div class="flex gap-2 justify-center pt-4">
        <button 
            onclick="window.print()"
            class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition"
        >
            🖨️ Print Label
        </button>
        
        <button 
            onclick="navigator.clipboard.writeText('{{ $record->barcode }}')"
            class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition"
        >
            📋 Copy Barcode
        </button>
    </div>
</div>

<style>
    @media print {
        body * {
            visibility: hidden;
        }
        .print-area, .print-area * {
            visibility: visible;
        }
        .print-area {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
        }
    }
</style>
