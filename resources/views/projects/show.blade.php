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
        </div>
    </div>
</x-app-layout>
