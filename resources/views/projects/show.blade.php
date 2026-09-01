<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $project->name }}
            </h2>
            @can('update', $project)
                <a href="{{ route('projects.edit', $project) }}">
                    <x-secondary-button>{{ __('Edit Project') }}</x-secondary-button>
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="p-4 bg-green-100 text-green-800 rounded-md">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <span class="text-xs uppercase tracking-wide text-gray-500">
                    {{ App\Models\Project::STATUS_LABELS[$project->status] }}
                </span>
                <p class="mt-2 text-gray-700 whitespace-pre-line">{{ $project->description ?: __('No description.') }}</p>
            </div>

            @can('create', [App\Models\Specification::class, $project])
                @php $hasAiKey = auth()->user()?->hasAiConfigured(); @endphp
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-1">{{ __('Import from PDF') }}</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        {{ __('Upload a PDF and AI will read it and draft a specification and/or user stories into this project (whichever the document contains). Everything created is editable afterward.') }}
                    </p>

                    @unless ($hasAiKey)
                        <p class="mb-3 text-sm text-gray-500">{{ __('Add an AI API key in your profile to enable this.') }}</p>
                    @endunless

                    <form method="POST" action="{{ route('ai.import-pdf', $project) }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
                        @csrf
                        <input
                            type="file"
                            name="document"
                            accept="application/pdf"
                            @disabled(! $hasAiKey)
                            class="text-sm text-gray-700 file:mr-3 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-xs file:font-semibold file:uppercase file:tracking-widest file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200 disabled:opacity-50"
                        >
                        <x-primary-button type="submit" :disabled="! $hasAiKey">{{ __('Import') }}</x-primary-button>
                    </form>
                    <x-input-error :messages="$errors->get('document')" class="mt-2" />

                    @if ($latestImport)
                        <div class="mt-4">
                            @if ($latestImport->isPending() || $latestImport->isProcessing())
                                <x-ai-pending :request="$latestImport" :message="__('Reading your PDF and drafting content… this can take up to a minute. The page will update automatically.')" />
                            @elseif ($latestImport->isFailed())
                                <p class="text-sm text-red-700">{{ __('Import failed: :error', ['error' => $latestImport->error_message]) }}</p>
                            @elseif ($latestImport->isCompleted())
                                <p class="text-sm text-green-700">{{ $latestImport->response }}</p>
                            @endif
                        </div>
                    @endif
                </div>
            @endcan

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('Specifications') }}</h3>
                    @can('create', [App\Models\Specification::class, $project])
                        <a href="{{ route('projects.specifications.create', $project) }}">
                            <x-primary-button>{{ __('New Specification') }}</x-primary-button>
                        </a>
                    @endcan
                </div>

                @if ($project->specifications->isEmpty())
                    <p class="text-gray-600">{{ __('No specifications yet.') }}</p>
                @else
                    <ul class="divide-y divide-gray-200">
                        @foreach ($project->specifications as $specification)
                            <li class="py-3 flex justify-between items-center">
                                <a href="{{ route('specifications.show', $specification) }}" class="text-gray-900 hover:underline">
                                    {{ $specification->title }}
                                </a>
                                <span class="text-xs text-gray-500">v{{ $specification->current_version }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('User Stories') }}</h3>
                    @can('create', [App\Models\UserStory::class, $project])
                        <a href="{{ route('projects.user-stories.create', $project) }}">
                            <x-primary-button>{{ __('New User Story') }}</x-primary-button>
                        </a>
                    @endcan
                </div>

                @if ($project->userStories->isEmpty())
                    <p class="text-gray-600">{{ __('No user stories yet.') }}</p>
                @else
                    <ul class="divide-y divide-gray-200">
                        @foreach ($project->userStories as $userStory)
                            <li class="py-3 flex justify-between items-center">
                                <a href="{{ route('user-stories.show', $userStory) }}" class="text-gray-900 hover:underline">
                                    {{ $userStory->title }}
                                </a>
                                <span class="text-xs text-gray-500">v{{ $userStory->current_version }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
