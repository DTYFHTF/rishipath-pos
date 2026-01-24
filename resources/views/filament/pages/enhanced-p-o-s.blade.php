<x-filament-panels::page>
    <style>
        /* Ensure search dropdown hover uses dark background in dark mode */
        .dark .pos-search-item:hover,
        .dark .pos-customer-item:hover {
            background-color: rgba(55,65,81,1) !important; /* Tailwind gray-700 */
            color: rgb(255 255 255 / 1) !important;
        }
    </style>
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
                        {{ ($session['status'] ?? null) === 'parked' ? 'opacity-60' : '' }}"
                >
                    <x-heroicon-o-shopping-cart class="w-4 h-4" />
                    <span class="font-medium">{{ $session['name'] }}</span>
                    @if(count($session['cart']) > 0)
                        <span class="px-2 py-0.5 text-xs rounded-full bg-white/20">
                            {{ count($session['cart']) }}
                        </span>
                    @endif
                    @if(($session['status'] ?? null) === 'parked')
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
            <div class="flex items-center justify-end mb-2">
                <div class="text-sm text-gray-500">Session: <span class="font-medium">{{ $session['name'] }}</span></div>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                {{-- Left: Product Search & Cart --}}
                <div class="lg:col-span-2 space-y-4">
                    
                    {{-- Quick Search with Keyboard Shortcut Hint --}}
                    <x-filament::card>
                        <div x-data="{ showResults: false }" class="relative">
                            <div class="flex items-center gap-3">
                                <div class="flex-1 relative">
                                    <input
                                        type="text"
                                        wire:model.live.debounce.300ms="quickSearchInput"
                                        wire:keydown.enter="handleQuickInput"
                                        wire:keydown.escape="$set('quickSearchInput', '')"
                                        @input="showResults = ($event.target.value.length >= 1)"
                                        @focus="showResults = ($event.target.value.length >= 1)"
                                        @click.outside="showResults = false"
                                        placeholder="🔍 Search product name, Hindi/Sanskrit name, description, SKU, barcode..."
                                        class="w-full px-4 py-3 rounded-lg border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:border-primary-500 dark:focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20"
                                        autofocus
                                        x-ref="searchInput"
                                    />
                                </div>
                                <button
                                    wire:click="handleQuickInput"
                                    class="px-6 py-3 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition font-medium flex items-center gap-2"
                                >
                                    <x-heroicon-o-magnifying-glass class="w-5 h-5" />
                                    <kbd class="px-2 py-1 text-xs bg-white/20 rounded border border-white/30 text-white">
                                        Enter
                                    </kbd>
                                </button>
                            </div>

                            {{-- Search Results Dropdown --}}
                            @php $searchResults = $this->searchResults; @endphp
                            @if(strlen($quickSearchInput ?? '') >= 1 && count($searchResults) > 0)
                                <div 
                                    x-show="showResults"
                                    x-transition
                                    class="absolute z-50 w-full mt-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-xl max-h-80 overflow-y-auto"
                                >
                                    @forelse($searchResults as $result)
                                        <button
                                            type="button"
                                            wire:click="addToCart({{ $result['id'] }})"
                                            @click="showResults = false"
                                            class="w-full px-4 py-3 text-left hover:bg-gray-100 dark:hover:bg-gray-700 border-b border-gray-100 dark:border-gray-700 last:border-b-0 flex items-center gap-3 pos-search-item"
                                        >
                                            {{-- Product Image --}}
                                            @if(!empty($result['image']))
                                                <img src="{{ Storage::url($result['image']) }}" alt="" class="w-12 h-12 object-cover rounded">
                                            @else
                                                <div class="w-12 h-12 bg-gray-200 dark:bg-gray-700 rounded flex items-center justify-center">
                                                    <x-heroicon-o-cube class="w-6 h-6 text-gray-400" />
                                                </div>
                                            @endif
                                            
                                            <div class="flex-1 min-w-0">
                                                <div class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ $result['product_name'] }}</div>
                                                <div class="text-sm text-gray-500 dark:text-gray-400">{{ $result['variant_name'] }}</div>
                                                @if($result['other_names'])
                                                    <div class="text-xs text-blue-600 dark:text-blue-400 truncate">{{ $result['other_names'] }}</div>
                                                @endif
                                            </div>
                                            
                                            <div class="text-right">
                                                <div class="font-bold text-green-600 dark:text-green-400">₹{{ number_format($result['price'], 2) }}</div>
                                                <div class="text-xs text-gray-400">{{ $result['sku'] }}</div>
                                                <div class="text-xs mt-0.5 {{ ($result['available_stock'] ?? 0) > 0 ? 'text-emerald-500' : 'text-red-500' }}">
                                                    @if(($result['reserved_stock'] ?? 0) > 0)
                                                        <span class="font-medium">{{ $result['available_stock'] ?? 0 }}</span> / {{ $result['total_stock'] ?? 0 }}
                                                        <span class="text-orange-500">({{ $result['reserved_stock'] }} reserved)</span>
                                                    @else
                                                        <span class="font-medium">{{ $result['available_stock'] ?? 0 }}</span> / {{ $result['total_stock'] ?? 0 }}
                                                    @endif
                                                </div>
                                            </div>
                                        </button>
                                    @empty
                                        <div class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                                            <x-heroicon-o-magnifying-glass class="w-8 h-8 mx-auto mb-2 opacity-50" />
                                            <p>No products found for "{{ $quickSearchInput }}"</p>
                                            <p class="text-xs mt-1">Try searching by name, Hindi/Sanskrit name, or SKU</p>
                                        </div>
                                    @endforelse
                                </div>
                            @endif
                        </div>

                        {{-- Keyboard Shortcuts Info --}}
                        <div class="mt-3 flex flex-wrap gap-2 text-xs text-gray-500 dark:text-gray-400">
                            <span><kbd class="px-1.5 py-0.5 bg-white dark:bg-gray-800 rounded border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white shadow-sm">F1</kbd> New Cart</span>
                            <span><kbd class="px-1.5 py-0.5 bg-white dark:bg-gray-800 rounded border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white shadow-sm">F2</kbd> Park</span>
                            <span><kbd class="px-1.5 py-0.5 bg-white dark:bg-gray-800 rounded border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white shadow-sm">F8</kbd> Complete</span>
                            <span><kbd class="px-1.5 py-0.5 bg-white dark:bg-gray-800 rounded border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white shadow-sm">F9</kbd> Clear</span>
                            <span><kbd class="px-1.5 py-0.5 bg-white dark:bg-gray-800 rounded border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white shadow-sm">Esc</kbd> Clear Search</span>
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
                                        {{-- Product Image --}}
                                        @if(!empty($item['image']))
                                            <img src="{{ Storage::url($item['image']) }}" alt="{{ $item['product_name'] }}" class="w-12 h-12 object-cover rounded">
                                        @else
                                            <div class="w-12 h-12 bg-gray-200 dark:bg-gray-700 rounded flex items-center justify-center">
                                                <x-heroicon-o-photo class="w-6 h-6 text-gray-400" />
                                            </div>
                                        @endif

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
                                                class="w-16 px-2 py-1 text-center rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100"
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
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Customer (Optional)</label>
                            <button
                                wire:click="openCustomerModal"
                                class="text-xs px-2 py-1 bg-primary-600 text-white rounded hover:bg-primary-700 transition"
                                title="Add New Customer"
                            >
                                + New
                            </button>
                        </div>
                        
                        {{-- Show selected customer or search input --}}
                        @if($session['customer_id'])
                            <div class="flex items-center justify-between p-3 bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-700 rounded-lg">
                                <div class="flex items-center gap-2">
                                    <span class="text-xl">👤</span>
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-gray-100">{{ $session['customer_name'] ?? 'Customer' }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            ID: {{ $session['customer_id'] }}
                                            @if(!empty($session['customer_phone']))
                                                • {{ $session['customer_phone'] }}
                                            @endif
                                            @if(!empty($session['customer_email']))
                                                • {{ $session['customer_email'] }}
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <button 
                                    type="button"
                                    wire:click="clearCustomer"
                                    class="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                                    title="Remove customer"
                                >
                                    ✕
                                </button>
                            </div>
                        @else
                            <div x-data="{ open: false }" class="relative" wire:key="customer-search-{{ $activeSessionKey }}">
                                <input
                                    type="text"
                                    wire:model.live.debounce.300ms="customerSearch"
                                    @focus="open = true"
                                    @click.away="open = false"
                                    @input="open = true"
                                    placeholder="🔍 Search customers (name, phone)..."
                                    class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder-gray-400"
                                />
                                
                                {{-- Loading indicator --}}
                                <div wire:loading wire:target="customerSearch" class="absolute right-3 top-1/2 -translate-y-1/2">
                                    <svg class="animate-spin h-4 w-4 text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </div>
                                
                                {{-- Dropdown results --}}
                                <div 
                                    x-show="open"
                                    x-transition
                                    class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg max-h-60 overflow-y-auto"
                                >
                                    {{-- Walk-in option always first --}}
                                    <button
                                        type="button"
                                        wire:click="selectCustomer(null)"
                                        @click="open = false"
                                        class="w-full px-4 py-3 text-left hover:bg-gray-100 dark:hover:bg-gray-700 border-b border-gray-100 dark:border-gray-700 flex items-center gap-3 transition pos-customer-item"
                                    >
                                        <span class="text-xl">🚶</span>
                                        <div>
                                            <div class="font-medium text-gray-900 dark:text-gray-100">Walk-in Customer</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">No customer account</div>
                                        </div>
                                    </button>
                                    
                                    @forelse($this->customers as $customer)
                                        <button
                                            type="button"
                                            wire:click="selectCustomer({{ $customer->id }})"
                                            @click="open = false"
                                            class="w-full px-4 py-3 text-left hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-3 transition pos-customer-item"
                                        >
                                            <span class="text-xl">👤</span>
                                            <div class="flex-1">
                                                <div class="font-medium text-gray-900 dark:text-gray-100">{{ $customer->name }}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $customer->phone ?? 'No phone' }}
                                                    @if($customer->total_purchases > 0)
                                                        • {{ $customer->total_purchases }} purchases
                                                    @endif
                                                </div>
                                            </div>
                                            @if($customer->loyalty_points > 0)
                                                <span class="text-xs bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 px-2 py-1 rounded">
                                                    {{ $customer->loyalty_points }} pts
                                                </span>
                                            @endif
                                        </button>
                                    @empty
                                        @if($customerSearch)
                                            <div class="px-4 py-3 text-gray-500 dark:text-gray-400 text-center">
                                                No customers found for "{{ $customerSearch }}"
                                            </div>
                                        @endif
                                    @endforelse
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                @if($customerSearch)
                                    Showing search results (up to 10)
                                @else
                                    Top 5 customers by purchase count shown
                                @endif
                            </p>
                        @endif
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

                    {{-- Payment Section - Optimized Layout --}}
                    <x-filament::card>
                        <div class="flex items-start gap-4">
                            {{-- Left: Payment Method (Compact) --}}
                            <div class="flex-1">
                                <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Payment</label>
                                <div class="grid grid-cols-2 gap-2">
                                    @foreach(['cash' => 'Cash', 'card' => 'Card', 'upi' => 'UPI', 'credit' => 'Credit'] as $method => $label)
                                        <button
                                            wire:click="$set('sessions.{{ $activeSessionKey }}.payment_method', '{{ $method }}')"
                                            class="px-3 py-2 text-sm rounded-lg border-2 transition font-medium
                                                {{ $session['payment_method'] === $method 
                                                    ? 'border-primary-600 bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-400' 
                                                    : 'border-gray-300 dark:border-gray-600 hover:border-gray-400 dark:hover:border-gray-500 text-gray-700 dark:text-gray-300' 
                                                }}"
                                        >
                                            {{ $label }}
                                        </button>
                                    @endforeach
                                </div>
                                
                                <button
                                    wire:click="openSplitPayment"
                                    class="mt-2 w-full px-3 py-1.5 text-xs border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg hover:border-primary-500 hover:text-primary-600 dark:hover:text-primary-400 transition font-medium text-gray-600 dark:text-gray-400"
                                >
                                    <x-heroicon-o-squares-plus class="w-3 h-3 inline mr-1" />
                                    Split Payment
                                </button>
                            </div>

                            {{-- Right: Amount Received (for cash) --}}
                            @if($session['payment_method'] === 'cash')
                                <div class="flex-1">
                                    <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Amount Received</label>
                                    <input
                                        type="number"
                                        wire:model.live="sessions.{{ $activeSessionKey }}.amount_received"
                                        step="0.01"
                                        placeholder="0.00"
                                        class="w-full px-3 py-2 text-lg rounded-lg border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:border-primary-500 dark:focus:border-primary-500"
                                    />
                                    
                                    @if($session['amount_received'] > 0)
                                        <div class="mt-2 p-2 bg-green-50 dark:bg-green-900/20 rounded-lg">
                                            <div class="flex justify-between text-green-700 dark:text-green-400">
                                                <span class="text-xs font-medium">Change:</span>
                                                <span class="text-lg font-bold">
                                                    ₹{{ number_format(max(0, $session['amount_received'] - $session['total']), 2) }}
                                                </span>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </x-filament::card>

                    {{-- WhatsApp Receipt Toggle --}}
                    @if(!empty($session['customer_id']) && !empty($session['customer_phone']))
                        <x-filament::card>
                            <label class="flex items-center cursor-pointer">
                                <input
                                    type="checkbox"
                                    wire:model="sendWhatsApp"
                                    class="h-5 w-5 text-primary-600 border-gray-300 rounded focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                                />
                                <div class="ml-3 flex-1">
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Send receipt via WhatsApp</span>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                        To: {{ $session['customer_phone'] ?? '' }}
                                    </p>
                                </div>
                                <x-heroicon-o-chat-bubble-left-right class="w-5 h-5 text-green-600 dark:text-green-400" />
                            </label>
                        </x-filament::card>
                    @endif

                    {{-- Action Buttons --}}
                    <div class="space-y-2">
                        <button
                            wire:click="completeSale"
                            {{ empty($session['cart']) ? 'disabled' : '' }}
                            style="background-color:#16a34a !important; color:#ffffff !important; border-color: transparent !important;"
                            class="w-full px-6 py-4 hover:bg-green-700 text-white dark:text-white rounded-lg transition font-bold text-lg disabled:opacity-50 disabled:cursor-not-allowed shadow-lg"
                            aria-label="Complete Sale (F8)"
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
                <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-[9999]" wire:click.self="$set('showSplitPayment', false)">
                    <div class="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto" role="dialog" aria-modal="true">
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
                                class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-200/30 dark:hover:bg-gray-700/50 transition-colors"
                            >
                                Cancel
                            </button>
                            <button
                                wire:click="completeSale"
                                class="flex-1 px-4 py-2 bg-green-600 text-white dark:bg-green-600 dark:text-white rounded-lg hover:bg-green-700 shadow-md transition font-medium"
                                style="background-color:#16a34a;color:#ffffff;"
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
