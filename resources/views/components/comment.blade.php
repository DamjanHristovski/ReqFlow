@props(['comment', 'specification', 'depth' => 0])

<li class="py-3">
    <div class="flex justify-between items-start gap-4">
        <div class="flex-1">
            <div class="text-sm">
                <span class="font-medium text-gray-900">{{ $comment->user?->name ?? __('Deleted user') }}</span>
                <span class="ms-2 text-gray-500">{{ $comment->created_at->format('M j, Y g:ia') }}</span>
            </div>
            <p class="mt-1 text-gray-900 whitespace-pre-line">{{ $comment->body }}</p>

            @can('create', [App\Models\Comment::class, $specification])
                <div class="mt-1" x-data="{ showReplyForm: false }">
                    <button type="button" class="text-sm text-indigo-600 hover:underline" x-on:click="showReplyForm = ! showReplyForm">
                        {{ __('Reply') }}
                    </button>

                    <div x-show="showReplyForm" x-cloak class="mt-2">
                        <form method="POST" action="{{ route('comments.store', $specification) }}">
                            @csrf
                            <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                            <textarea name="body" rows="2" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="{{ __('Write a reply...') }}"></textarea>
                            <div class="flex justify-end mt-2">
                                <x-primary-button>{{ __('Post Reply') }}</x-primary-button>
                            </div>
                        </form>
                    </div>
                </div>
            @endcan
        </div>

        @can('delete', $comment)
            <x-danger-button x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-comment-deletion-{{ $comment->id }}')">
                {{ __('Delete') }}
            </x-danger-button>
        @endcan
    </div>

    @if ($comment->replies->isNotEmpty())
        @if ($depth === 0)
            <div x-data="{ showReplies: false }" class="mt-2">
                <button type="button" class="text-sm text-gray-600 hover:underline" x-on:click="showReplies = ! showReplies">
                    <span x-show="! showReplies" x-cloak>{{ __('View replies (:count)', ['count' => $comment->totalReplyCount()]) }}</span>
                    <span x-show="showReplies" x-cloak>{{ __('Hide replies') }}</span>
                </button>

                <ul x-show="showReplies" x-cloak class="mt-3 ms-6 space-y-3 border-l-2 border-gray-100 pl-4">
                    @foreach ($comment->replies as $reply)
                        <x-comment :comment="$reply" :specification="$specification" :depth="$depth + 1" />
                    @endforeach
                </ul>
            </div>
        @else
            <ul class="mt-3 ms-6 space-y-3 border-l-2 border-gray-100 pl-4">
                @foreach ($comment->replies as $reply)
                    <x-comment :comment="$reply" :specification="$specification" :depth="$depth + 1" />
                @endforeach
            </ul>
        @endif
    @endif
</li>
