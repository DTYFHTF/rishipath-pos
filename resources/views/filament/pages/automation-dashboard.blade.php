<x-filament-panels::page>
    {{-- Stats Overview --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        @php
            $stats = $this->getStats();
        @endphp
        
        <x-filament::card>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Schedules</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $stats['active_schedules'] }}</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500">of {{ $stats['total_schedules'] }} total</p>
                </div>
                <div class="p-3 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                    <x-heroicon-o-calendar class="w-8 h-8 text-blue-600 dark:text-blue-400" />
                </div>
            </div>
        </x-filament::card>

        <x-filament::card>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Alerts</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $stats['active_alerts'] }}</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500">of {{ $stats['total_alerts'] }} total</p>
                </div>
                <div class="p-3 bg-yellow-100 dark:bg-yellow-900/30 rounded-lg">
                    <x-heroicon-o-bell-alert class="w-8 h-8 text-yellow-600 dark:text-yellow-400" />
                </div>
            </div>
        </x-filament::card>

        <x-filament::card>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Success Rate</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $stats['success_rate'] }}%</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500">Last 7 days</p>
                </div>
                <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-lg">
                    <x-heroicon-o-chart-bar class="w-8 h-8 text-green-600 dark:text-green-400" />
                </div>
            </div>
        </x-filament::card>

        <x-filament::card>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Critical Alerts</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $stats['critical_alerts'] }}</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500">Last 24 hours</p>
                </div>
                <div class="p-3 bg-red-100 dark:bg-red-900/30 rounded-lg">
                    <x-heroicon-o-fire class="w-8 h-8 text-red-600 dark:text-red-400" />
                </div>
            </div>
        </x-filament::card>
    </div>

    {{-- Main Content Grid --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Upcoming Schedules --}}
        <x-filament::card>
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Upcoming Scheduled Reports</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">Next reports due to run</p>
            </div>
            
            @php
                $upcoming = $this->getUpcomingSchedules();
            @endphp
            
            @if(count($upcoming) > 0)
                <div class="space-y-3">
                    @foreach($upcoming as $schedule)
                        <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $schedule['name'] }}</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $schedule['report_type'] }} • {{ $schedule['frequency'] }}</p>
                                </div>
                                <span class="text-xs text-gray-400 dark:text-gray-500">
                                    {{ $schedule['next_run_at']->diffForHumans() }}
                                </span>
                            </div>
                            <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                Recipients: {{ $schedule['recipients_count'] }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">No upcoming schedules</p>
            @endif
        </x-filament::card>

        {{-- Recent Report Runs --}}
        <x-filament::card>
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Recent Report Runs</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">Latest execution history</p>
            </div>
            
            @php
                $runs = $this->getRecentRuns();
            @endphp
            
            @if(count($runs) > 0)
                <div class="space-y-3">
                    @foreach($runs as $run)
                        <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $run['schedule_name'] }}</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ $run['records'] ?? 0 }} records
                                        @if($run['file_size'])
                                            • {{ $run['file_size'] }}
                                        @endif
                                    </p>
                                </div>
                                <span class="px-2 py-1 text-xs font-medium rounded-full
                                    @if($run['status'] === 'completed') bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-400
                                    @elseif($run['status'] === 'failed') bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-400
                                    @elseif($run['status'] === 'running') bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-400
                                    @else bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-300
                                    @endif">
                                    {{ ucfirst($run['status']) }}
                                </span>
                            </div>
                            <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                {{ $run['created_at']->diffForHumans() }}
                                @if($run['duration'])
                                    • {{ $run['duration'] }}s
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">No recent runs</p>
            @endif
        </x-filament::card>

        {{-- Alert Summary --}}
        <x-filament::card>
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Alert Summary</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">Active alert rules breakdown</p>
            </div>
            
            @php
                $alertSummary = $this->getAlertSummary();
            @endphp
            
            <div class="space-y-4">
                @if(count($alertSummary['by_type']) > 0)
                    <div>
                        <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">By Type</p>
                        <div class="space-y-2">
                            @foreach($alertSummary['by_type'] as $type => $count)
                                <div class="flex items-center justify-between p-2 bg-gray-50 dark:bg-gray-800 rounded">
                                    <span class="text-sm text-gray-900 dark:text-gray-100">{{ ucwords(str_replace('_', ' ', $type)) }}</span>
                                    <span class="px-2 py-1 text-xs font-medium text-blue-800 dark:text-blue-400 bg-blue-100 dark:bg-blue-900/30 rounded-full">{{ $count }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </x-filament::card>

        {{-- Recent Notifications --}}
        <x-filament::card>
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Recent Notifications</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">Latest alerts sent</p>
            </div>
            
            @php
                $notifications = $this->getRecentNotifications();
            @endphp
            
            @if(count($notifications) > 0)
                <div class="space-y-3">
                    @foreach($notifications as $notification)
                        <div class="p-3 border-l-4 rounded-lg bg-gray-50 dark:bg-gray-800
                            @if($notification['severity'] === 'critical') border-red-500 dark:border-red-400
                            @elseif($notification['severity'] === 'error') border-orange-500 dark:border-orange-400
                            @elseif($notification['severity'] === 'warning') border-yellow-500 dark:border-yellow-400
                            @else border-blue-500 dark:border-blue-400
                            @endif">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $notification['title'] }}</p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ Str::limit($notification['message'], 80) }}</p>
                                </div>
                                @if($notification['sent'])
                                    <x-heroicon-o-check-circle class="w-5 h-5 text-green-500 dark:text-green-400" />
                                @else
                                    <x-heroicon-o-clock class="w-5 h-5 text-gray-400 dark:text-gray-500" />
                                @endif
                            </div>
                            <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                {{ $notification['created_at']->diffForHumans() }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">No recent notifications</p>
            @endif
        </x-filament::card>
    </div>
</x-filament-panels::page>
