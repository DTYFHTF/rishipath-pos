<x-filament-panels::page>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Transfer Form -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Transfer Stock Between Stores</h3>
            
            <form wire:submit="submitTransfer" class="space-y-4">
                {{ $this->form }}
                
                <div class="flex justify-end pt-4">
                    <button 
                        type="submit"
                        class="px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition font-medium"
                    >
                        Transfer Stock
                    </button>
                </div>
            </form>
        </div>

        <!-- Recent Transfers -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Recent Transfers</h3>
            
            <div class="space-y-3">
                @forelse($this->getRecentTransfers() as $transfer)
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-3 hover:bg-gray-200/30 dark:hover:bg-gray-700/50 transition-colors">
                        <div class="flex items-center justify-between mb-1">
                            <div class="font-medium text-sm">
                                {{ $transfer->productVariant->product->name ?? 'Unknown' }}
                            </div>
                            <span class="text-xs text-gray-500">
                                {{ $transfer->created_at->diffForHumans() }}
                            </span>
                        </div>
                        <div class="text-xs text-gray-600 dark:text-gray-400">
                            {{ $transfer->productVariant->pack_size ?? '' }}{{ $transfer->productVariant->unit ?? '' }}
                        </div>
                        <div class="flex items-center justify-between mt-2 text-sm">
                            <div>
                                <span class="text-gray-500">Qty:</span>
                                <span class="font-semibold {{ $transfer->quantity > 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $transfer->quantity }}
                                </span>
                            </div>
                            <div class="text-xs">
                                {{ $transfer->store->name ?? '' }}
                            </div>
                        </div>
                        @if($transfer->notes)
                            <div class="text-xs text-gray-500 mt-1 italic">{{ $transfer->notes }}</div>
                        @endif
                    </div>
                @empty
                    <div class="text-center text-gray-500 py-8">
                        No recent transfers
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-filament-panels::page>
