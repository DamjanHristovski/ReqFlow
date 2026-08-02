<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Teams') }}
            </h2>
            <a href="{{ route('teams.create') }}">
                <x-primary-button>{{ __('Create Team') }}</x-primary-button>
            </a>
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
                @if ($teams->isEmpty())
                    <div class="p-6 text-gray-600">
                        {{ __("You're not a member of any team yet.") }}
                    </div>
                @else
                    <ul class="divide-y divide-gray-200">
                        @foreach ($teams as $team)
                            <li class="p-6 flex justify-between items-center">
                                <div>
                                    <a href="{{ route('teams.show', $team) }}" class="text-lg font-medium text-gray-900 hover:underline">
                                        {{ $team->name }}
                                    </a>
                                    <span class="ms-2 text-xs uppercase tracking-wide text-gray-500">
                                        {{ $team->pivot->role }}
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
