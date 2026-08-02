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
            @can('update', $specification)
                <a href="{{ route('specifications.edit', $specification) }}">
                    <x-secondary-button>{{ __('Edit') }}</x-secondary-button>
                </a>
            @endcan
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
        </div>
    </div>
</x-app-layout>
