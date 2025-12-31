<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Filters -->
        <x-filament::card>
            <form wire:submit.prevent="$refresh">
                {{ $this->form }}
                <div class="mt-4">
                    <x-filament::button type="submit" wire:click="$refresh">
                        Generate Report
                    </x-filament::button>
                </div>
            </form>
        </x-filament::card>

        @php
            $data = $this->getSalesData();
        @endphp

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <x-filament::card>
                <div class="text-sm text-gray-600 dark:text-gray-400">Total Sales</div>
                <div class="text-3xl font-bold text-primary-600">₹{{ number_format($data['total_sales'], 2) }}</div>
            </x-filament::card>

            <x-filament::card>
                <div class="text-sm text-gray-600 dark:text-gray-400">Total Transactions</div>
                <div class="text-3xl font-bold text-primary-600">{{ number_format($data['total_transactions']) }}</div>
            </x-filament::card>

            <x-filament::card>
                <div class="text-sm text-gray-600 dark:text-gray-400">Average Sale</div>
                <div class="text-3xl font-bold text-primary-600">₹{{ number_format($data['average_sale'], 2) }}</div>
            </x-filament::card>

            <x-filament::card>
                <div class="text-sm text-gray-600 dark:text-gray-400">Items Sold</div>
                <div class="text-3xl font-bold text-primary-600">{{ number_format($data['total_items_sold']) }}</div>
            </x-filament::card>

            <x-filament::card>
                <div class="text-sm text-gray-600 dark:text-gray-400">Total Tax Collected</div>
                <div class="text-3xl font-bold text-primary-600">₹{{ number_format($data['total_tax'], 2) }}</div>
            </x-filament::card>

            <x-filament::card>
                <div class="text-sm text-gray-600 dark:text-gray-400">Total Discounts</div>
                <div class="text-3xl font-bold text-primary-600">₹{{ number_format($data['total_discount'], 2) }}</div>
            </x-filament::card>
        </div>

        <!-- Payment Methods Breakdown -->
        <x-filament::card>
            <h3 class="text-lg font-semibold mb-4">Sales by Payment Method</h3>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b dark:border-gray-700">
                            <th class="text-left py-2">Payment Method</th>
                            <th class="text-right py-2">Transactions</th>
                            <th class="text-right py-2">Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->getSalesByPaymentMethod() as $payment)
                            <tr class="border-b dark:border-gray-700">
                                <td class="py-2">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-200">
                                        {{ ucfirst($payment->payment_method) }}
                                    </span>
                                </td>
                                <td class="text-right py-2">{{ number_format($payment->count) }}</td>
                                <td class="text-right py-2 font-semibold">₹{{ number_format($payment->total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::card>

        <!-- Top Products -->
        <x-filament::card>
            <h3 class="text-lg font-semibold mb-4">Top 10 Products</h3>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b dark:border-gray-700">
                            <th class="text-left py-2">Product Name</th>
                            <th class="text-right py-2">Quantity Sold</th>
                            <th class="text-right py-2">Total Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->getTopProducts() as $product)
                            <tr class="border-b dark:border-gray-700">
                                <td class="py-2">{{ $product->product_name }}</td>
                                <td class="text-right py-2">{{ number_format($product->total_quantity, 2) }}</td>
                                <td class="text-right py-2 font-semibold">₹{{ number_format($product->total_revenue, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::card>

        <!-- Daily Sales Chart -->
        <x-filament::card>
            <h3 class="text-lg font-semibold mb-4">Daily Sales Trend</h3>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b dark:border-gray-700">
                            <th class="text-left py-2">Date</th>
                            <th class="text-right py-2">Transactions</th>
                            <th class="text-right py-2">Total Sales</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->getDailySales() as $day)
                            <tr class="border-b dark:border-gray-700">
                                <td class="py-2">{{ \Carbon\Carbon::parse($day->date)->format('M d, Y') }}</td>
                                <td class="text-right py-2">{{ number_format($day->count) }}</td>
                                <td class="text-right py-2 font-semibold">₹{{ number_format($day->total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::card>
    </div>
</x-filament-panels::page>
