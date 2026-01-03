<x-filament-panels::page>
    <div class="space-y-4" x-data="posSystem()" x-init="init()" @keydown.window="handleKeyboard($event)">
        
        {{-- Session Tabs --}}
        <div class="flex items-center gap-2 overflow-x-auto pb-2">
            @foreach($sessions as $key => $session)
                <button
                    wire:click="switchToSession('{{ $key }}')"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg transition whitespace-nowrap
                        {{ $activeSessionKey === $key 
                            ? 'bg-primary-600 text-white shadow-lg' 
                            : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700' 
                        }}
                        {{ $session['status'] === 'parked' ? 'opacity-60' : '' }}"
                >
                    <x-heroicon-o-shopping-cart class="w-4 h-4" />
                    <span class="font-medium">{{ $session['name'] }}</span>
                    @if(count($session['cart']) > 0)
                        <span class="px-2 py-0.5 text-xs rounded-full bg-white/20">
                            {{ count($session['cart']) }}
                        </span>
                    @endif
                    @if($session['status'] === 'parked')
                        <x-heroicon-o-pause class="w-3 h-3" />
                    @endif
                </button>
            @endforeach

            @if(count($sessions) < 5)
                <button
                    wire:click="createSession"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400 hover:border-primary-500 hover:text-primary-600 transition"
                >
                    <x-heroicon-o-plus class="w-5 h-5" />
                    <span>New Cart</span>
                </button>
            @endif
        </div>

        @php
            $session = $activeSessionKey && isset($sessions[$activeSessionKey]) ? $sessions[$activeSessionKey] : null;
        @endphp

        @if($session)
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                {{-- Left: Product Search & Cart --}}
                <div class="lg:col-span-2 space-y-4">
                    
                    {{-- Quick Search with Keyboard Shortcut Hint --}}
                    <x-filament::card>
                        <div class="flex items-center gap-3">
                            <div class="flex-1 relative">
                                <input
                                    type="text"
                                    wire:model.live="quickSearchInput"
                                    wire:keydown.enter="handleQuickInput"
                                    placeholder="Type product name, SKU, or scan barcode... (Press / to focus)"
                                    class="w-full px-4 py-3 pr-24 rounded-lg border-2 border-gray-300 dark:border-gray-600 focus:border-primary-500 dark:focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20"
                                    autofocus
                                    x-ref="searchInput"
                                />
                                <kbd class="absolute right-3 top-1/2 -translate-y-1/2 px-2 py-1 text-xs bg-gray-100 dark:bg-gray-700 rounded border border-gray-300 dark:border-gray-600">
                                    Enter
                                </kbd>
                            </div>
                            <button
                                wire:click="handleQuickInput"
                                class="px-6 py-3 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition font-medium"
                            >
                                <x-heroicon-o-magnifying-glass class="w-5 h-5" />
                            </button>
                        </div>

                        {{-- Keyboard Shortcuts Info --}}
                        <div class="mt-3 flex flex-wrap gap-2 text-xs text-gray-500 dark:text-gray-400">
                            <span><kbd class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-700 rounded">F1</kbd> New Cart</span>
                            <span><kbd class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-700 rounded">F2</kbd> Park</span>
                            <span><kbd class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-700 rounded">F8</kbd> Complete</span>
                            <span><kbd class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-700 rounded">F9</kbd> Clear</span>
                            <span><kbd class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-700 rounded">Esc</kbd> Clear Search</span>
                        </div>
                    </x-filament::card>

                    {{-- Cart Items --}}
                    <x-filament::card>
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Cart Items</h3>
                            <div class="flex items-center gap-2">
                                <button
                                    wire:click="parkSession"
                                    class="px-3 py-1.5 text-sm bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400 rounded-lg hover:bg-yellow-200 dark:hover:bg-yellow-900/50 transition"
                                >
                                    <x-heroicon-o-pause class="w-4 h-4 inline mr-1" />
                                    Park (F2)
                                </button>
                            </div>
                        </div>

                        @if(empty($session['cart']))
                            <div class="py-12 text-center text-gray-400 dark:text-gray-500">
                                <x-heroicon-o-shopping-cart class="w-16 h-16 mx-auto mb-3 opacity-50" />
                                <p class="text-lg">Cart is empty</p>
                                <p class="text-sm">Scan or search for products to add</p>
                            </div>
                        @else
                            <div class="space-y-2 max-h-96 overflow-y-auto">
                                @foreach($session['cart'] as $index => $item)
                                    <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                                        <div class="flex-1">
                                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $item['product_name'] }}</p>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $item['variant_name'] }} • ₹{{ number_format($item['price'], 2) }}</p>
                                        </div>

                                        {{-- Quantity Controls --}}
                                        <div class="flex items-center gap-2">
                                            <button
                                                wire:click="updateQuantity({{ $index }}, {{ max(1, $item['quantity'] - 1) }})"
                                                class="p-1.5 rounded bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600"
                                            >
                                                <x-heroicon-o-minus class="w-4 h-4" />
                                            </button>
                                            
                                            <input
                                                type="number"
                                                wire:change="updateQuantity({{ $index }}, $event.target.value)"
                                                value="{{ $item['quantity'] }}"
                                                class="w-16 px-2 py-1 text-center rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800"
                                                min="1"
                                            />
                                            
                                            <button
                                                wire:click="updateQuantity({{ $index }}, {{ $item['quantity'] + 1 }})"
                                                class="p-1.5 rounded bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600"
                                            >
                                                <x-heroicon-o-plus class="w-4 h-4" />
                                            </button>
                                        </div>

                                        <div class="text-right min-w-[100px]">
                                            <p class="font-semibold text-gray-900 dark:text-gray-100">
                                                ₹{{ number_format($item['price'] * $item['quantity'], 2) }}
                                            </p>
                                        </div>

                                        <button
                                            wire:click="removeItem({{ $index }})"
                                            class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded"
                                        >
                                            <x-heroicon-o-trash class="w-5 h-5" />
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </x-filament::card>
                </div>

                {{-- Right: Payment Panel --}}
                <div class="space-y-4">
                    {{-- Customer Selection --}}
                    <x-filament::card>
                        <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Customer (Optional)</label>
                        <select
                            wire:model.live="sessions.{{ $activeSessionKey }}.customer_id"
                            class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800"
                        >
                            <option value="">Walk-in Customer</option>
                            @foreach(\App\Models\Customer::where('active', true)->orderBy('name')->get() as $customer)
                                <option value="{{ $customer->id }}">{{ $customer->name }} - {{ $customer->phone }}</option>
                            @endforeach
                        </select>
                    </x-filament::card>

                    {{-- Totals --}}
                    <x-filament::card>
                        <div class="space-y-3">
                            <div class="flex justify-between text-gray-600 dark:text-gray-400">
                                <span>Subtotal:</span>
                                <span class="font-medium">₹{{ number_format($session['subtotal'] ?? 0, 2) }}</span>
                            </div>
                            
                            @if($session['discount'] > 0)
                                <div class="flex justify-between text-green-600 dark:text-green-400">
                                    <span>Discount:</span>
                                    <span class="font-medium">-₹{{ number_format($session['discount'], 2) }}</span>
                                </div>
                            @endif
                            
                            <div class="flex justify-between text-gray-600 dark:text-gray-400">
                                <span>Tax:</span>
                                <span class="font-medium">₹{{ number_format($session['tax'] ?? 0, 2) }}</span>
                            </div>
                            
                            <div class="pt-3 border-t-2 border-gray-200 dark:border-gray-700 flex justify-between text-xl font-bold text-gray-900 dark:text-gray-100">
                                <span>Total:</span>
                                <span>₹{{ number_format($session['total'] ?? 0, 2) }}</span>
                            </div>
                        </div>
                    </x-filament::card>

                    {{-- Payment Method --}}
                    <x-filament::card>
                        <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Payment Method</label>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach(['cash' => 'Cash', 'card' => 'Card', 'upi' => 'UPI', 'credit' => 'Credit'] as $method => $label)
                                <button
                                    wire:click="$set('sessions.{{ $activeSessionKey }}.payment_method', '{{ $method }}')"
                                    class="px-4 py-3 rounded-lg border-2 transition font-medium
                                        {{ $session['payment_method'] === $method 
                                            ? 'border-primary-600 bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-400' 
                                            : 'border-gray-300 dark:border-gray-600 hover:border-gray-400 dark:hover:border-gray-500' 
                                        }}"
                                >
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>

                        <button
                            wire:click="openSplitPayment"
                            class="mt-3 w-full px-4 py-2 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg hover:border-primary-500 hover:text-primary-600 dark:hover:text-primary-400 transition text-sm font-medium"
                        >
                            <x-heroicon-o-squares-plus class="w-4 h-4 inline mr-1" />
                            Split Payment
                        </button>
                    </x-filament::card>

                    {{-- Amount Received (for cash) --}}
                    @if($session['payment_method'] === 'cash')
                        <x-filament::card>
                            <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Amount Received</label>
                            <input
                                type="number"
                                wire:model.live="sessions.{{ $activeSessionKey }}.amount_received"
                                step="0.01"
                                placeholder="0.00"
                                class="w-full px-4 py-3 text-lg rounded-lg border-2 border-gray-300 dark:border-gray-600 focus:border-primary-500 dark:focus:border-primary-500"
                            />
                            
                            @if($session['amount_received'] > 0)
                                <div class="mt-3 p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                                    <div class="flex justify-between text-green-700 dark:text-green-400">
                                        <span>Change:</span>
                                        <span class="text-xl font-bold">
                                            ₹{{ number_format(max(0, $session['amount_received'] - $session['total']), 2) }}
                                        </span>
                                    </div>
                                </div>
                            @endif
                        </x-filament::card>
                    @endif

                    {{-- Action Buttons --}}
                    <div class="space-y-2">
                        <button
                            wire:click="completeSale"
                            {{ empty($session['cart']) ? 'disabled' : '' }}
                            class="w-full px-6 py-4 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-bold text-lg disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <x-heroicon-o-check-circle class="w-6 h-6 inline mr-2" />
                            Complete Sale (F8)
                        </button>

                        <button
                            wire:click="closeSession('{{ $activeSessionKey }}')"
                            class="w-full px-4 py-2 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 rounded-lg hover:bg-red-200 dark:hover:bg-red-900/50 transition font-medium"
                        >
                            Clear Cart (F9)
                        </button>
                    </div>
                </div>
            </div>

            {{-- Split Payment Modal --}}
            @if($showSplitPayment)
                <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" wire:click.self="$set('showSplitPayment', false)">
                    <div class="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                        <h3 class="text-xl font-bold mb-4 text-gray-900 dark:text-gray-100">Split Payment</h3>
                        
                        <div class="mb-4 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                            <div class="flex justify-between items-center">
                                <span class="text-blue-900 dark:text-blue-100">Total Amount:</span>
                                <span class="text-2xl font-bold text-blue-900 dark:text-blue-100">₹{{ number_format($session['total'], 2) }}</span>
                            </div>
                        </div>

                        <div class="space-y-3 mb-4">
                            @foreach($splitPayments as $index => $split)
                                <div class="flex gap-3 items-end">
                                    <div class="flex-1">
                                        <label class="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">Method</label>
                                        <select
                                            wire:model="splitPayments.{{ $index }}.method"
                                            class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800"
                                        >
                                            <option value="cash">Cash</option>
                                            <option value="card">Card</option>
                                            <option value="upi">UPI</option>
                                            <option value="bank_transfer">Bank Transfer</option>
                                            <option value="cheque">Cheque</option>
                                        </select>
                                    </div>
                                    
                                    <div class="flex-1">
                                        <label class="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">Amount</label>
                                        <input
                                            type="number"
                                            wire:model="splitPayments.{{ $index }}.amount"
                                            step="0.01"
                                            placeholder="0.00"
                                            class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800"
                                        />
                                    </div>
                                    
                                    <div class="flex-1">
                                        <label class="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">Reference</label>
                                        <input
                                            type="text"
                                            wire:model="splitPayments.{{ $index }}.reference"
                                            placeholder="Optional"
                                            class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800"
                                        />
                                    </div>

                                    @if(count($splitPayments) > 1)
                                        <button
                                            wire:click="removePaymentMethod({{ $index }})"
                                            class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded"
                                        >
                                            <x-heroicon-o-trash class="w-5 h-5" />
                                        </button>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <button
                            wire:click="addPaymentMethod"
                            class="w-full px-4 py-2 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg hover:border-primary-500 hover:text-primary-600 dark:hover:text-primary-400 transition font-medium mb-4"
                        >
                            <x-heroicon-o-plus class="w-4 h-4 inline mr-1" />
                            Add Payment Method
                        </button>

                        <div class="flex gap-3">
                            <button
                                wire:click="$set('showSplitPayment', false)"
                                class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition"
                            >
                                Cancel
                            </button>
                            <button
                                wire:click="completeSale"
                                class="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium"
                            >
                                Complete Payment
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        @endif
    </div>

    <script>
        function posSystem() {
            return {
                init() {
                    // Focus search on load
                    this.$nextTick(() => {
                        if (this.$refs.searchInput) {
                            this.$refs.searchInput.focus();
                        }
                    });
                },
                
                handleKeyboard(event) {
                    // F1 - New Cart
                    if (event.key === 'F1') {
                        event.preventDefault();
                        @this.call('createSession');
                    }
                    
                    // F2 - Park Session
                    if (event.key === 'F2') {
                        event.preventDefault();
                        @this.call('parkSession');
                    }
                    
                    // F8 - Complete Sale
                    if (event.key === 'F8') {
                        event.preventDefault();
                        @this.call('completeSale');
                    }
                    
                    // F9 - Clear Cart
                    if (event.key === 'F9') {
                        event.preventDefault();
                        if (confirm('Clear the current cart?')) {
                            @this.call('closeSession', @this.activeSessionKey);
                        }
                    }
                    
                    // / - Focus search
                    if (event.key === '/' && !event.target.matches('input, textarea')) {
                        event.preventDefault();
                        this.$refs.searchInput?.focus();
                    }
                    
                    // Esc - Clear search or close modals
                    if (event.key === 'Escape') {
                        if (this.$refs.searchInput === document.activeElement) {
                            this.$refs.searchInput.value = '';
                            @this.set('quickSearchInput', '');
                        }
                    }
                }
            };
        }
    </script>
</x-filament-panels::page>
