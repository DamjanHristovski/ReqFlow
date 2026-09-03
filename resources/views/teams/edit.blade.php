<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Team') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('teams.update', $team) }}">
                    @csrf
                    @method('PATCH')

                    <div>
                        <x-input-label for="name" :value="__('Team Name')" />
                        <x-text-input id="name" name="name" type="text" class="block mt-1 w-full" :value="old('name', $team->name)" required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div class="flex items-center justify-end mt-4">
                        <x-primary-button>{{ __('Save') }}</x-primary-button>
                    </div>
                </form>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900">{{ __('Delete Team') }}</h3>
                <p class="mt-1 text-sm text-gray-600">
                    {{ __('Once a team is deleted, all of its resources will be permanently unavailable to its members.') }}
                </p>

                <x-danger-button x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-team-deletion')" class="mt-4">
                    {{ __('Delete Team') }}
                </x-danger-button>
            </div>
        </div>
    </div>

    <x-modal name="confirm-team-deletion" focusable>
        <form method="POST" action="{{ route('teams.destroy', $team) }}" class="p-6">
            @csrf
            @method('DELETE')

            <h2 class="text-lg font-medium text-gray-900">
                {{ __('Delete this team?') }}
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                {{ __('Once a team is deleted, all of its resources will be permanently unavailable to its members. This cannot be undone.') }}
            </p>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button type="button" x-on:click="$dispatch('close')">
                    {{ __('Cancel') }}
                </x-secondary-button>
                <x-danger-button type="submit">
                    {{ __('Delete Team') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</x-app-layout>
