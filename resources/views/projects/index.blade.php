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

            @if (!$projects->isEmpty())
                <div x-data="{
                        open: false,
                        statuses: ['all'],
                        toggle(status) {
                            if (status === 'all') {
                                this.statuses = ['all'];
                                return;
                            }
                            this.statuses = this.statuses.filter(s => s !== 'all');
                            this.statuses = this.statuses.includes(status)
                                ? this.statuses.filter(s => s !== status)
                                : [...this.statuses, status];
                            if (this.statuses.length === 0) {
                                this.statuses = ['all'];
                            }
                        },
                        isChecked(status) { return this.statuses.includes(status); },
                        isVisible(status) { return this.statuses.includes('all') || this.statuses.includes(status); },
                     }">
                    <div class="relative mb-4" @click.outside="open = false">
                        <button type="button" @click="open = ! open"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">
                            {{ __('Filter by status') }}
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div x-show="open" x-cloak
                             class="absolute z-50 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5">
                            <ul class="py-2">
                                <li>
                                    <label class="flex items-center gap-2 px-4 py-1.5 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
                                        <input type="checkbox" :checked="isChecked('all')" @click="toggle('all')"
                                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                        {{ __('All') }}
                                    </label>
                                </li>
                                @foreach (App\Models\Project::STATUSES as $status)
                                    <li>
                                        <label class="flex items-center gap-2 px-4 py-1.5 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
                                            <input type="checkbox" :checked="isChecked('{{ $status }}')" @click="toggle('{{ $status }}')"
                                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                            {{ App\Models\Project::STATUS_LABELS[$status] }}
                                        </label>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>

                    @php $projectsByStatus = $projects->groupBy('status'); @endphp
                    <div class="space-y-6">
                        @foreach (App\Models\Project::STATUSES as $status)
                            @continue($projectsByStatus->get($status, collect())->isEmpty())
                            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg" x-show="isVisible('{{ $status }}')">
                                <div class="px-6 py-4 border-b border-gray-200">
                                    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">
                                        {{ App\Models\Project::STATUS_LABELS[$status] }}
                                        <span class="text-gray-400 font-normal">({{ $projectsByStatus->get($status)->count() }})</span>
                                    </h3>
                                </div>
                                <ul class="divide-y divide-gray-200">
                                    @foreach ($projectsByStatus->get($status) as $project)
                                        <li class="p-6 flex justify-between items-center">
                                            <a href="{{ route('projects.show', $project) }}" class="text-lg font-medium text-gray-900 hover:underline">
                                                {{ $project->name }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-600">
                        {{ __('No projects yet.') }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
