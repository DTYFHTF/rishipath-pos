<div class="space-y-4">
    @forelse($getState() as $reply)
        <div class="flex gap-3 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="flex-shrink-0">
                <div class="w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center text-primary-700 dark:text-primary-300 font-semibold">
                    {{ strtoupper(substr($reply->user->name ?? 'U', 0, 1)) }}
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-2 mb-2">
                    <div class="flex items-center gap-2">
                        <span class="font-semibold text-gray-900 dark:text-white">
                            {{ $reply->user->name ?? 'Unknown User' }}
                        </span>
                        @if($reply->user_id === $reply->feedbackable->user_id ?? null)
                            <span class="text-xs px-2 py-0.5 rounded bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300">
                                Author
                            </span>
                        @endif
                        @if($reply->user->isSuperAdmin() ?? false)
                            <span class="text-xs px-2 py-0.5 rounded bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300">
                                Admin
                            </span>
                        @endif
                    </div>
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $reply->created_at->diffForHumans() }}
                    </span>
                </div>
                <div class="text-sm text-gray-700 dark:text-gray-300 prose prose-sm dark:prose-invert max-w-none">
                    {{ $reply->message }}
                </div>
                @if($reply->attachments && count($reply->attachments) > 0)
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach($reply->attachments as $file)
                            <a href="/storage/{{ $file }}" 
                               target="_blank" 
                               class="text-xs px-2 py-1 rounded bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600 flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                </svg>
                                {{ basename($file) }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div class="text-center text-gray-500 dark:text-gray-400 py-8">
            No replies yet
        </div>
    @endforelse
</div>
