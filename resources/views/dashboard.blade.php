<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Teams --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 flex justify-between items-center border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('Your Teams') }}</h3>
                    <a href="{{ route('teams.index') }}" class="text-sm text-indigo-600 hover:underline">
                        {{ __('View all') }} &rarr;
                    </a>
                </div>
                @if ($teams->isEmpty())
                    <div class="p-6 text-gray-600">
                        {{ __("You're not a member of any team yet.") }}
                        <a href="{{ route('teams.create') }}" class="text-indigo-600 hover:underline">{{ __('Create one') }}</a>.
                    </div>
                @else
                    <ul class="divide-y divide-gray-200">
                        @foreach ($teams as $team)
                            <li class="p-6 flex justify-between items-center">
                                <a href="{{ route('teams.show', $team) }}" class="text-gray-900 font-medium hover:underline">
                                    {{ $team->name }}
                                </a>
                                <div class="flex items-center gap-4 text-sm text-gray-500">
                                    <span>{{ trans_choice('{0} No projects|{1} :count project|[2,*] :count projects', $team->projects_count, ['count' => $team->projects_count]) }}</span>
                                    <span class="uppercase tracking-wide text-xs">{{ $team->pivot->role }}</span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Recent Projects --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('Recent Projects') }}</h3>
                </div>
                @if ($recentProjects->isEmpty())
                    <div class="p-6 text-gray-600">
                        {{ __('No projects yet.') }}
                    </div>
                @else
                    <ul class="divide-y divide-gray-200">
                        @foreach ($recentProjects as $project)
                            <li class="p-6 flex justify-between items-center">
                                <div>
                                    <a href="{{ route('projects.show', $project) }}" class="text-gray-900 font-medium hover:underline">
                                        {{ $project->name }}
                                    </a>
                                    <span class="ms-2 text-xs uppercase tracking-wide text-gray-500">
                                        {{ \App\Models\Project::STATUS_LABELS[$project->status] }}
                                    </span>
                                </div>
                                <span class="text-sm text-gray-500">{{ $project->team->name }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Recent Specifications --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('Recently Updated Specifications') }}</h3>
                </div>
                @if ($recentSpecifications->isEmpty())
                    <div class="p-6 text-gray-600">
                        {{ __('No specifications yet.') }}
                    </div>
                @else
                    <ul class="divide-y divide-gray-200">
                        @foreach ($recentSpecifications as $specification)
                            <li class="p-6 flex justify-between items-center">
                                <div>
                                    <a href="{{ route('specifications.show', $specification) }}" class="text-gray-900 font-medium hover:underline">
                                        {{ $specification->title }}
                                    </a>
                                    <span class="ms-2 text-xs uppercase tracking-wide text-gray-500">
                                        {{ __('v:number', ['number' => $specification->current_version]) }}
                                    </span>
                                </div>
                                <span class="text-sm text-gray-500">{{ $specification->project->name }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Recent Comments --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('Recent Comments') }}</h3>
                </div>
                @if ($recentComments->isEmpty())
                    <div class="p-6 text-gray-600">
                        {{ __('No comments yet.') }}
                    </div>
                @else
                    <ul class="divide-y divide-gray-200">
                        @foreach ($recentComments as $comment)
                            <li class="p-6">
                                <div class="flex justify-between items-baseline">
                                    <span class="text-sm font-medium text-gray-900">{{ $comment->user->name }}</span>
                                    <span class="text-xs text-gray-500">{{ $comment->created_at->diffForHumans() }}</span>
                                </div>
                                <p class="mt-1 text-gray-700 line-clamp-2">{{ $comment->body }}</p>
                                <a href="{{ route('specifications.show', $comment->specification) }}" class="text-sm text-indigo-600 hover:underline">
                                    {{ __('on :title', ['title' => $comment->specification->title]) }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
