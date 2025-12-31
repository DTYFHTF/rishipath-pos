<x-filament-panels::page>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Side: Product Search & Cart -->
        <div class="lg:col-span-2 space-y-4">
            <!-- Product Search -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4">Product Search</h3>
                
                <input 
                    type="text" 
                    wire:model.live="searchQuery"
                    placeholder="Search by name, SKU, or barcode..."
                    class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 focus:ring-primary-500 focus:border-primary-500"
                    autofocus
                />

                @if($searchQuery)
                    <div class="mt-4 space-y-2 max-h-64 overflow-y-auto">
                        @foreach($this->searchProducts() as $variant)
                            <button 
                                wire:click="addToCart({{ $variant->id }})"
                                class="w-full text-left p-3 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600"
                            >
                                <div class="font-semibold">{{ $variant->product->name }}</div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ $variant->pack_size }} {{ $variant->unit }} - SKU: {{ $variant->sku }}
                                </div>
                                <div class="text-sm font-semibold text-primary-600">
                                    ₹{{ number_format($variant->mrp_india ?? $variant->base_price, 2) }}
                                </div>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Shopping Cart -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4">Shopping Cart</h3>
                
                @if(empty($cart))
                    <p class="text-gray-500 dark:text-gray-400 text-center py-8">
                        Cart is empty. Search and add products above.
                    </p>
                @else
                    <div class="space-y-3">
                        @foreach($cart as $key => $item)
                            <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <div class="flex-1">
                                    <div class="font-semibold">{{ $item['product_name'] }}</div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">
                                        ₹{{ number_format($item['price'], 2) }} × {{ $item['quantity'] }} {{ $item['unit'] }}
                                    </div>
                                </div>
                                
                                <div class="flex items-center gap-2">
                                    <button 
                                        wire:click="updateQuantity('{{ $key }}', {{ $item['quantity'] - 1 }})"
                                        class="p-1 hover:bg-gray-200 dark:hover:bg-gray-600 rounded"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
                                        </svg>
                                    </button>
                                    
                                    <span class="w-12 text-center font-semibold">{{ $item['quantity'] }}</span>
                                    
                                    <button 
                                        wire:click="updateQuantity('{{ $key }}', {{ $item['quantity'] + 1 }})"
                                        class="p-1 hover:bg-gray-200 dark:hover:bg-gray-600 rounded"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                        </svg>
                                    </button>
                                </div>
                                
                                <div class="font-semibold w-24 text-right">
                                    ₹{{ number_format($item['price'] * $item['quantity'], 2) }}
                                </div>
                                
                                <button 
                                    wire:click="removeFromCart('{{ $key }}')"
                                    class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                        @endforeach
                    </div>

                    <button 
                        wire:click="clearCart"
                        class="mt-4 w-full py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400"
                    >
                        Clear Cart
                    </button>
                @endif
            </div>
        </div>

        <!-- Right Side: Customer & Payment -->
        <div class="space-y-4">
            <!-- Customer Info -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4">Customer Details</h3>
                
                <div class="space-y-3">
                    <input 
                        type="text" 
                        wire:model="customerName"
                        placeholder="Customer Name (Optional)"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                    />
                    
                    <input 
                        type="tel" 
                        wire:model="customerPhone"
                        placeholder="Phone Number (Optional)"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                    />
                </div>
            </div>

            <!-- Payment Summary -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4">Payment Summary</h3>
                
                <div class="space-y-2 mb-4">
                    <div class="flex justify-between">
                        <span>Subtotal:</span>
                        <span class="font-semibold">₹{{ number_format($this->getSubtotal(), 2) }}</span>
                    </div>
                    <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400">
                        <span>Tax (GST):</span>
                        <span>₹{{ number_format($this->getTaxAmount(), 2) }}</span>
                    </div>
                    <div class="flex justify-between text-lg font-bold border-t pt-2 mt-2">
                        <span>Total:</span>
                        <span class="text-primary-600">₹{{ number_format($this->getTotal(), 2) }}</span>
                    </div>
                </div>

                <div class="space-y-3">
                    <select 
                        wire:model="paymentMethod"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                    >
                        <option value="cash">Cash</option>
                        <option value="upi">UPI</option>
                        <option value="card">Card</option>
                        <option value="esewa">eSewa</option>
                        <option value="khalti">Khalti</option>
                    </select>

                    @if($paymentMethod === 'cash')
                        <div>
                            <label class="block text-sm font-medium mb-1">Amount Received</label>
                            <input 
                                type="number" 
                                wire:model.live="amountReceived"
                                step="0.01"
                                placeholder="0.00"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                            />
                        </div>

                        @if($amountReceived > 0)
                            <div class="flex justify-between text-lg font-semibold {{ $this->getChangeAmount() < 0 ? 'text-red-600' : 'text-green-600' }}">
                                <span>Change:</span>
                                <span>₹{{ number_format($this->getChangeAmount(), 2) }}</span>
                            </div>
                        @endif
                    @endif

                    <textarea 
                        wire:model="notes"
                        placeholder="Notes (Optional)"
                        rows="2"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                    ></textarea>
                </div>
            </div>

            <!-- Complete Sale Button -->
            <button 
                wire:click="completeSale"
                @if(empty($cart)) disabled @endif
                class="w-full py-4 bg-primary-600 hover:bg-primary-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-bold text-lg rounded-lg transition"
            >
                Complete Sale
            </button>
        </div>
    </div>
</x-filament-panels::page>
