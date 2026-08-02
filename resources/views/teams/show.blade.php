<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $team->name }}
            </h2>
            @can('update', $team)
                <a href="{{ route('teams.edit', $team) }}">
                    <x-secondary-button>{{ __('Edit Team') }}</x-secondary-button>
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

            @if ($errors->any())
                <div class="p-4 bg-red-100 text-red-800 rounded-md">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __('Members') }}</h3>

                <ul class="divide-y divide-gray-200">
                    @foreach ($team->members as $member)
                        <li class="py-3 flex items-center justify-between">
                            <div>
                                <span class="font-medium text-gray-900">{{ $member->name }}</span>
                                <span class="text-sm text-gray-500">{{ $member->email }}</span>
                                <span class="ms-2 text-xs uppercase tracking-wide text-gray-500">{{ $member->pivot->role }}</span>
                            </div>

                            <div class="flex items-center gap-2">
                                @can('updateMemberRole', $team)
                                    <form method="POST" action="{{ route('teams.members.update', [$team, $member]) }}">
                                        @csrf
                                        @method('PATCH')
                                        <select name="role" onchange="this.form.submit()" class="rounded-md border-gray-300 text-sm">
                                            <option value="member" @selected($member->pivot->role === 'member')>{{ __('Member') }}</option>
                                            <option value="owner" @selected($member->pivot->role === 'owner')>{{ __('Owner') }}</option>
                                        </select>
                                    </form>
                                @endcan

                                @if ($member->id === auth()->id() || auth()->user()->can('removeMember', $team))
                                    <form method="POST" action="{{ route('teams.members.destroy', [$team, $member]) }}" onsubmit="return confirm('{{ __('Are you sure?') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <x-danger-button>
                                            {{ $member->id === auth()->id() ? __('Leave') : __('Remove') }}
                                        </x-danger-button>
                                    </form>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>

            @can('addMember', $team)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __('Add Member') }}</h3>

                    <form method="POST" action="{{ route('teams.members.store', $team) }}" class="flex items-end gap-4">
                        @csrf
                        <div class="flex-1">
                            <x-input-label for="email" :value="__('User Email')" />
                            <x-text-input id="email" name="email" type="email" class="block mt-1 w-full" required />
                        </div>
                        <x-primary-button>{{ __('Add') }}</x-primary-button>
                    </form>
                </div>
            @endcan
        </div>
    </div>
</x-app-layout>
