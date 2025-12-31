<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Stats Overview --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow">
                <div class="text-sm text-gray-500 dark:text-gray-400">Total Members</div>
                <div class="text-3xl font-bold mt-2">{{ number_format($this->getStats()['total_members']) }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow">
                <div class="text-sm text-gray-500 dark:text-gray-400">Active Members (90d)</div>
                <div class="text-3xl font-bold mt-2 text-green-600">{{ number_format($this->getStats()['active_members']) }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow">
                <div class="text-sm text-gray-500 dark:text-gray-400">Points Issued</div>
                <div class="text-3xl font-bold mt-2 text-blue-600">{{ number_format($this->getStats()['points_issued']) }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow">
                <div class="text-sm text-gray-500 dark:text-gray-400">Points Outstanding</div>
                <div class="text-3xl font-bold mt-2 text-purple-600">{{ number_format($this->getStats()['points_outstanding']) }}</div>
            </div>
        </div>

        {{-- Tier Distribution --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow">
            <h2 class="text-lg font-semibold mb-4">Loyalty Tiers</h2>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                @foreach($this->getTiers() as $tier)
                    <div class="border dark:border-gray-700 rounded-lg p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <x-filament::badge color="{{ $tier['badge_color'] }}">
                                {{ $tier['name'] }}
                            </x-filament::badge>
                        </div>
                        <div class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                            {{ $tier['min_points'] }}{{ $tier['max_points'] ? ' - ' . $tier['max_points'] : '+' }} points
                        </div>
                        <div class="text-xs">
                            <div>Multiplier: {{ $tier['points_multiplier'] }}x</div>
                            <div>Discount: {{ $tier['discount_percentage'] }}%</div>
                        </div>
                        @if(isset($this->getStats()['tier_distribution'][$tier['name']]))
                            <div class="mt-2 text-2xl font-bold text-primary-600">
                                {{ $this->getStats()['tier_distribution'][$tier['name']] }}
                            </div>
                            <div class="text-xs text-gray-500">members</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Top Members --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow">
            <h2 class="text-lg font-semibold mb-4">Top Loyalty Members</h2>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b dark:border-gray-700">
                            <th class="text-left py-2">Rank</th>
                            <th class="text-left py-2">Customer</th>
                            <th class="text-left py-2">Tier</th>
                            <th class="text-right py-2">Points</th>
                            <th class="text-left py-2">Enrolled</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->getTopMembers() as $index => $customer)
                            <tr class="border-b dark:border-gray-700">
                                <td class="py-2">
                                    @if($index < 3)
                                        <span class="text-2xl">{{ ['🥇', '🥈', '🥉'][$index] }}</span>
                                    @else
                                        <span class="text-gray-500">#{{ $index + 1 }}</span>
                                    @endif
                                </td>
                                <td class="py-2">
                                    <div class="font-medium">{{ $customer['name'] }}</div>
                                    <div class="text-xs text-gray-500">{{ $customer['phone'] }}</div>
                                </td>
                                <td class="py-2">
                                    @if($customer['loyalty_tier'])
                                        <x-filament::badge color="{{ $customer['loyalty_tier']['badge_color'] }}">
                                            {{ $customer['loyalty_tier']['name'] }}
                                        </x-filament::badge>
                                    @endif
                                </td>
                                <td class="py-2 text-right font-bold">{{ number_format($customer['loyalty_points']) }}</td>
                                <td class="py-2 text-sm text-gray-500">
                                    {{ \Carbon\Carbon::parse($customer['loyalty_enrolled_at'])->format('M Y') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Recent Activity --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow">
            <h2 class="text-lg font-semibold mb-4">Recent Points Activity</h2>
            <div class="space-y-2">
                @foreach($this->getRecentActivity() as $activity)
                    <div class="flex items-center justify-between border-b dark:border-gray-700 pb-2">
                        <div class="flex-1">
                            <div class="font-medium">{{ $activity['customer']['name'] ?? 'Unknown' }}</div>
                            <div class="text-sm text-gray-500">{{ $activity['description'] }}</div>
                        </div>
                        <div class="text-right">
                            <div class="font-bold {{ $activity['points'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $activity['points'] > 0 ? '+' : '' }}{{ $activity['points'] }}
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ \Carbon\Carbon::parse($activity['created_at'])->diffForHumans() }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-filament-panels::page>
