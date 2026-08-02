<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ $specification->title }}
                </h2>
                <a href="{{ route('projects.show', $specification->project) }}" class="text-sm text-gray-500 hover:underline">
                    &larr; {{ $specification->project->name }}
                </a>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('specifications.versions.index', $specification) }}">
                    <x-secondary-button>{{ __('Version History') }}</x-secondary-button>
                </a>
                @can('update', $specification)
                    <a href="{{ route('specifications.edit', $specification) }}">
                        <x-secondary-button>{{ __('Edit') }}</x-secondary-button>
                    </a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-6 p-4 bg-green-100 text-green-800 rounded-md">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 space-y-6">
                <div class="text-xs text-gray-500">{{ __('Version') }} {{ $specification->current_version }}</div>

                <div>
                    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">{{ __('Description') }}</h3>
                    <p class="mt-1 text-gray-900 whitespace-pre-line">{{ $specification->description ?: __('—') }}</p>
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">{{ __('Goals') }}</h3>
                    <p class="mt-1 text-gray-900 whitespace-pre-line">{{ $specification->goals ?: __('—') }}</p>
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">{{ __('Scope') }}</h3>
                    <p class="mt-1 text-gray-900 whitespace-pre-line">{{ $specification->scope ?: __('—') }}</p>
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">{{ __('Functional Requirements') }}</h3>
                    <p class="mt-1 text-gray-900 whitespace-pre-line">{{ $specification->functional_requirements ?: __('—') }}</p>
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">{{ __('Non-Functional Requirements') }}</h3>
                    <p class="mt-1 text-gray-900 whitespace-pre-line">{{ $specification->non_functional_requirements ?: __('—') }}</p>
                </div>
            </div>

            <div class="mt-6 bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __('Comments') }}</h3>

                @if ($comments->isEmpty())
                    <p class="text-gray-600">{{ __('No comments yet.') }}</p>
                @else
                    <ul class="divide-y divide-gray-200">
                        @foreach ($comments as $comment)
                            <x-comment :comment="$comment" :specification="$specification" />
                        @endforeach
                    </ul>
                @endif

                @can('create', [App\Models\Comment::class, $specification])
                    <form method="POST" action="{{ route('comments.store', $specification) }}" class="mt-6">
                        @csrf
                        <x-input-label for="body" :value="__('Add a comment')" />
                        <textarea id="body" name="body" rows="3" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('body') }}</textarea>
                        <x-input-error :messages="$errors->get('body')" class="mt-2" />

                        <div class="flex justify-end mt-2">
                            <x-primary-button>{{ __('Post Comment') }}</x-primary-button>
                        </div>
                    </form>
                @endcan
            </div>
        </div>
    </div>

    @foreach ($allComments as $comment)
        @can('delete', $comment)
            <x-modal name="confirm-comment-deletion-{{ $comment->id }}" focusable>
                <form method="POST" action="{{ route('comments.destroy', $comment) }}" class="p-6">
                    @csrf
                    @method('DELETE')

                    <h2 class="text-lg font-medium text-gray-900">
                        {{ __('Delete this comment?') }}
                    </h2>

                    <p class="mt-1 text-sm text-gray-600">
                        @if ($comment->totalReplyCount() > 0)
                            {{ trans_choice('This will also delete :count reply|This will also delete :count replies', $comment->totalReplyCount(), ['count' => $comment->totalReplyCount()]) }}
                        @endif
                        {{ __('This cannot be undone.') }}
                    </p>

                    <div class="mt-6 flex justify-end gap-3">
                        <x-secondary-button type="button" x-on:click="$dispatch('close')">
                            {{ __('Cancel') }}
                        </x-secondary-button>
                        <x-danger-button type="submit">
                            {{ __('Delete') }}
                        </x-danger-button>
                    </div>
                </form>
            </x-modal>
        @endcan
    @endforeach
</x-app-layout>
