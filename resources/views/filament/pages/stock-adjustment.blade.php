<x-filament-panels::page>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Adjustment Form -->
        <div>
            <x-filament::card>
                <h3 class="text-lg font-semibold mb-4">Adjust Stock Level</h3>
                
                <form wire:submit.prevent="submitAdjustment">
                    {{ $this->form }}
                    
                    @if($quantity)
                        <div class="mt-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <div class="flex justify-between items-center">
                                <div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">Current Stock</div>
                                    <div class="text-2xl font-bold">{{ number_format($currentStock, 2) }}</div>
                                </div>
                                <div class="text-3xl text-gray-400">→</div>
                                <div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">New Stock</div>
                                    <div class="text-2xl font-bold text-primary-600">{{ number_format($this->getNewStockLevel(), 2) }}</div>
                                </div>
                            </div>
                            <div class="mt-2 text-sm">
                                <span class="font-semibold">Change:</span>
                                <span class="{{ ($this->getNewStockLevel() - $currentStock) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ ($this->getNewStockLevel() - $currentStock) >= 0 ? '+' : '' }}{{ number_format($this->getNewStockLevel() - $currentStock, 2) }}
                                </span>
                            </div>
                        </div>
                    @endif

                    <div class="mt-6">
                        <x-filament::button type="submit" class="w-full">
                            Apply Adjustment
                        </x-filament::button>
                    </div>
                </form>
            </x-filament::card>
        </div>

        <!-- Recent Adjustments -->
        <div>
            <x-filament::card>
                <h3 class="text-lg font-semibold mb-4">Recent Adjustments</h3>
                
                <div class="space-y-3">
                    @forelse($this->getRecentAdjustments() as $movement)
                        <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <div class="font-semibold">{{ $movement->productVariant->product->name }}</div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ $movement->productVariant->pack_size }}{{ $movement->productVariant->unit }}
                                    </div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        {{ $movement->user->name }} • {{ $movement->store->name }}
                                    </div>
                                    @if($movement->notes)
                                        <div class="text-sm text-gray-500 mt-1">{{ $movement->notes }}</div>
                                    @endif
                                </div>
                                <div class="text-right">
                                    <div class="text-sm text-gray-500">
                                        {{ $movement->created_at->diffForHumans() }}
                                    </div>
                                    <div class="mt-1">
                                        <span class="font-semibold">{{ number_format($movement->from_quantity, 2) }}</span>
                                        <span class="text-gray-400 mx-1">→</span>
                                        <span class="font-semibold text-primary-600">{{ number_format($movement->to_quantity, 2) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-center text-gray-500 py-8">No recent adjustments</p>
                    @endforelse
                </div>
            </x-filament::card>
        </div>
    </div>
</x-filament-panels::page>
