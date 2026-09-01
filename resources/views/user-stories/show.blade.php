<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ $userStory->title }}
                </h2>
                <a href="{{ route('projects.show', $userStory->project) }}" class="text-sm text-gray-500 hover:underline">
                    &larr; {{ $userStory->project->name }}
                </a>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('user-stories.versions.index', $userStory) }}">
                    <x-secondary-button>{{ __('Version History') }}</x-secondary-button>
                </a>
                @can('update', $userStory)
                    <a href="{{ route('user-stories.edit', $userStory) }}">
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
                <div class="text-xs text-gray-500">{{ __('Version') }} {{ $userStory->current_version }}</div>

                <div>
                    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">{{ __('Description') }}</h3>
                    <p class="mt-1 text-gray-900 whitespace-pre-line">{{ $userStory->description ?: __('—') }}</p>
                </div>
            </div>

            @php $hasAiKey = auth()->user()?->hasAiConfigured(); @endphp

            <div class="mt-6 bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="flex items-center justify-between mb-1">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('AI Review & Next Steps') }}</h3>
                    @can('update', $userStory)
                        <form method="POST" action="{{ route('user-stories.ai.generate-next-steps', $userStory) }}">
                            @csrf
                            <x-secondary-button type="submit" :disabled="! $hasAiKey" :title="! $hasAiKey ? __('Add an AI API key in your profile to enable this.') : false">
                                {{ $latestNextSteps && $latestNextSteps->isCompleted() ? __('Regenerate') : __('Generate') }}
                            </x-secondary-button>
                        </form>
                    @endcan
                </div>

                <p class="mb-3 text-sm text-gray-600">
                    {{ __('AI reviews this user story and lists missing information, next actions, risks, and stakeholder questions as read-only feedback that never changes your story.') }}
                </p>

                @unless ($hasAiKey)
                    <p class="mb-3 text-sm text-gray-500">{{ __('Add an AI API key in your profile to enable AI features.') }}</p>
                @endunless

                @if ($latestNextSteps)
                    @if ($latestNextSteps->isPending() || $latestNextSteps->isProcessing())
                        <x-ai-pending :request="$latestNextSteps" :message="__('AI is reviewing this user story… the page will update automatically.')" />
                    @elseif ($latestNextSteps->isFailed())
                        <p class="text-sm text-red-700">{{ __('AI request failed: :error', ['error' => $latestNextSteps->error_message]) }}</p>
                    @elseif ($latestNextSteps->isCompleted())
                        <div class="mt-2 rounded-md bg-gray-50 p-4 text-gray-900 whitespace-pre-line">{{ $latestNextSteps->response }}</div>
                    @endif
                @else
                    <p class="text-sm text-gray-500">{{ __('No review yet — click Generate.') }}</p>
                @endif
            </div>

            <div class="mt-6 bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('Acceptance Criteria') }}</h3>
                    @can('create', [App\Models\AcceptanceCriterion::class, $userStory])
                        <div class="flex items-center gap-2">
                            <form method="POST" action="{{ route('user-stories.ai.generate-acceptance-criteria', $userStory) }}">
                                @csrf
                                <x-secondary-button type="submit" :disabled="! $hasAiKey" :title="! $hasAiKey ? __('Add an AI API key in your profile to enable this.') : false">{{ __('Generate with AI') }}</x-secondary-button>
                            </form>
                            <a href="{{ route('user-stories.acceptance-criteria.create', $userStory) }}">
                                <x-primary-button>{{ __('New Acceptance Criterion') }}</x-primary-button>
                            </a>
                        </div>
                    @endcan
                </div>

                @if ($latestCriteria && ($latestCriteria->isPending() || $latestCriteria->isProcessing()))
                    <x-ai-pending class="mb-3" :request="$latestCriteria" :message="__('AI is generating acceptance criteria… the page will update automatically.')" />
                @elseif ($latestCriteria && $latestCriteria->isFailed())
                    <p class="mb-3 text-sm text-red-700">{{ __('AI request failed: :error', ['error' => $latestCriteria->error_message]) }}</p>
                @endif

                @if ($userStory->acceptanceCriteria->isEmpty())
                    <p class="text-gray-600">{{ __('No acceptance criteria yet.') }}</p>
                @else
                    <ul class="divide-y divide-gray-200">
                        @foreach ($userStory->acceptanceCriteria as $acceptanceCriterion)
                            <li class="py-3 flex justify-between items-center">
                                <a href="{{ route('acceptance-criteria.show', $acceptanceCriterion) }}" class="text-gray-900 hover:underline">
                                    {{ Illuminate\Support\Str::limit($acceptanceCriterion->description, 80) }}
                                </a>
                                <span class="text-xs uppercase tracking-wide {{ $acceptanceCriterion->status === App\Models\AcceptanceCriterion::STATUS_MET ? 'text-green-700' : 'text-gray-500' }}">
                                    {{ App\Models\AcceptanceCriterion::STATUS_LABELS[$acceptanceCriterion->status] }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="mt-6 bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __('Comments') }}</h3>

                @if ($comments->isEmpty())
                    <p class="text-gray-600">{{ __('No comments yet.') }}</p>
                @else
                    <ul class="divide-y divide-gray-200">
                        @foreach ($comments as $comment)
                            <x-comment :comment="$comment" :commentable="$userStory" />
                        @endforeach
                    </ul>
                @endif

                @can('create', [App\Models\Comment::class, $userStory])
                    <form method="POST" action="{{ route('user-stories.comments.store', $userStory) }}" class="mt-6">
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
