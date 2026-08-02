<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Specification') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('specifications.update', $specification) }}">
                    @csrf
                    @method('PATCH')

                    <div>
                        <x-input-label for="title" :value="__('Title')" />
                        <x-text-input id="title" name="title" type="text" class="block mt-1 w-full" :value="old('title', $specification->title)" required autofocus />
                        <x-input-error :messages="$errors->get('title')" class="mt-2" />
                    </div>

                    <x-ai-improvable-field field="description" :label="__('Description')" :specification="$specification" />

                    <x-ai-improvable-field field="goals" :label="__('Goals')" :specification="$specification" />

                    <x-ai-improvable-field field="scope" :label="__('Scope')" :specification="$specification" />

                    <x-ai-improvable-field field="functional_requirements" :label="__('Functional Requirements')" :rows="4" :specification="$specification" />

                    <x-ai-improvable-field field="non_functional_requirements" :label="__('Non-Functional Requirements')" :rows="4" :specification="$specification" />

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
                    <x-ai-assistance-panel field="description" :label="__('Description')" :specification="$specification" :ai-request="$latestAiRequests->get('description')" />
                    <x-ai-assistance-panel field="goals" :label="__('Goals')" :specification="$specification" :ai-request="$latestAiRequests->get('goals')" />
                    <x-ai-assistance-panel field="scope" :label="__('Scope')" :specification="$specification" :ai-request="$latestAiRequests->get('scope')" />
                    <x-ai-assistance-panel field="functional_requirements" :label="__('Functional Requirements')" :specification="$specification" :ai-request="$latestAiRequests->get('functional_requirements')" />
                    <x-ai-assistance-panel field="non_functional_requirements" :label="__('Non-Functional Requirements')" :specification="$specification" :ai-request="$latestAiRequests->get('non_functional_requirements')" />
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900">{{ __('Delete Specification') }}</h3>
                <p class="mt-1 text-sm text-gray-600">{{ __('This cannot be undone.') }}</p>

                <x-danger-button x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-specification-deletion')" class="mt-4">
                    {{ __('Delete Specification') }}
                </x-danger-button>
            </div>
        </div>
    </div>

    <x-modal name="confirm-specification-deletion" focusable>
        <form method="POST" action="{{ route('specifications.destroy', $specification) }}" class="p-6">
            @csrf
            @method('DELETE')

            <h2 class="text-lg font-medium text-gray-900">
                {{ __('Delete this specification?') }}
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                {{ __('This cannot be undone.') }}
            </p>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button type="button" x-on:click="$dispatch('close')">
                    {{ __('Cancel') }}
                </x-secondary-button>
                <x-danger-button type="submit">
                    {{ __('Delete Specification') }}
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

                    <form method="POST" action="{{ route('specifications.versions.restore', [$specification, session('matched_version_id')]) }}">
                        @csrf
                        <x-secondary-button type="submit">
                            {{ __('Restore to Version :version', ['version' => session('matched_version_number')]) }}
                        </x-secondary-button>
                    </form>

                    <form method="POST" action="{{ route('specifications.update', $specification) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="title" value="{{ old('title') }}">
                        <input type="hidden" name="description" value="{{ old('description') }}">
                        <input type="hidden" name="goals" value="{{ old('goals') }}">
                        <input type="hidden" name="scope" value="{{ old('scope') }}">
                        <input type="hidden" name="functional_requirements" value="{{ old('functional_requirements') }}">
                        <input type="hidden" name="non_functional_requirements" value="{{ old('non_functional_requirements') }}">
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
