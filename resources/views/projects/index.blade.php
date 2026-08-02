<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $team->name }} &mdash; {{ __('Projects') }}
            </h2>
            @can('create', [App\Models\Project::class, $team])
                <a href="{{ route('teams.projects.create', $team) }}">
                    <x-primary-button>{{ __('New Project') }}</x-primary-button>
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 p-4 bg-green-100 text-green-800 rounded-md">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                @if ($projects->isEmpty())
                    <div class="p-6 text-gray-600">
                        {{ __('No projects yet.') }}
                    </div>
                @else
                    <ul class="divide-y divide-gray-200">
                        @foreach ($projects as $project)
                            <li class="p-6 flex justify-between items-center">
                                <div>
                                    <a href="{{ route('projects.show', $project) }}" class="text-lg font-medium text-gray-900 hover:underline">
                                        {{ $project->name }}
                                    </a>
                                    <span class="ms-2 text-xs uppercase tracking-wide text-gray-500">
                                        {{ App\Models\Project::STATUS_LABELS[$project->status] }}
                                    </span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
