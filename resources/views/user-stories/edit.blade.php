<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit User Story') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('user-stories.update', $userStory) }}">
                    @csrf
                    @method('PATCH')

                    <div>
                        <x-input-label for="title" :value="__('Title')" />
                        <x-text-input id="title" name="title" type="text" class="block mt-1 w-full" :value="old('title', $userStory->title)" required autofocus />
                        <x-input-error :messages="$errors->get('title')" class="mt-2" />
                    </div>

                    <x-ai-improvable-field field="description" :label="__('Description')" :model="$userStory" />

                    <div class="flex items-center justify-end mt-4">
                        <x-primary-button>{{ __('Save') }}</x-primary-button>
                    </div>
                </form>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-2">{{ __('AI Assistance') }}</h3>
                <p class="text-sm text-gray-600 mb-2">
                    {{ __('Request an AI-improved rewrite of any field. This runs in the background — refresh the page to check for a result.') }}
                </p>

                <div class="divide-y divide-gray-200">
                    <x-ai-assistance-panel field="description" :label="__('Description')" :model="$userStory" :ai-request="$latestAiRequests->get('description')" />
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900">{{ __('Delete User Story') }}</h3>
                <p class="mt-1 text-sm text-gray-600">{{ __('This cannot be undone.') }}</p>

                <x-danger-button x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-user-story-deletion')" class="mt-4">
                    {{ __('Delete User Story') }}
                </x-danger-button>
            </div>
        </div>
    </div>

    <x-modal name="confirm-user-story-deletion" focusable>
        <form method="POST" action="{{ route('user-stories.destroy', $userStory) }}" class="p-6">
            @csrf
            @method('DELETE')

            <h2 class="text-lg font-medium text-gray-900">
                {{ __('Delete this user story?') }}
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                {{ __('This cannot be undone.') }}
            </p>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button type="button" x-on:click="$dispatch('close')">
                    {{ __('Cancel') }}
                </x-secondary-button>
                <x-danger-button type="submit">
                    {{ __('Delete User Story') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>

    @if (session('matched_version_number'))
        <x-modal name="confirm-restore-match" :show="true" focusable>
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    {{ __('This matches an earlier version') }}
                </h2>

                <p class="mt-1 text-sm text-gray-600">
                    {{ __('The content you entered is identical to Version :version. Would you like to restore to it instead of creating a duplicate version?', ['version' => session('matched_version_number')]) }}
                </p>

                <div class="mt-6 flex justify-end gap-3">
                    <x-secondary-button type="button" x-on:click="$dispatch('close')">
                        {{ __('Cancel') }}
                    </x-secondary-button>

                    <form method="POST" action="{{ route('user-stories.versions.restore', [$userStory, session('matched_version_id')]) }}">
                        @csrf
                        <x-secondary-button type="submit">
                            {{ __('Restore to Version :version', ['version' => session('matched_version_number')]) }}
                        </x-secondary-button>
                    </form>

                    <form method="POST" action="{{ route('user-stories.update', $userStory) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="title" value="{{ old('title') }}">
                        <input type="hidden" name="description" value="{{ old('description') }}">
                        <input type="hidden" name="force_new_version" value="1">
                        <x-primary-button type="submit">
                            {{ __('Save as new version anyway') }}
                        </x-primary-button>
                    </form>
                </div>
            </div>
        </x-modal>
    @endif
</x-app-layout>
