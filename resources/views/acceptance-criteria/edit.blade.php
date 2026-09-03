<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Acceptance Criterion') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('acceptance-criteria.update', $acceptanceCriterion) }}">
                    @csrf
                    @method('PATCH')

                    <x-ai-improvable-field field="description" :label="__('Description')" :model="$acceptanceCriterion" />

                    <div class="mt-4">
                        <x-input-label for="status" :value="__('Status')" />
                        <select id="status" name="status" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach (App\Models\AcceptanceCriterion::STATUSES as $status)
                                <option value="{{ $status }}" @selected(old('status', $acceptanceCriterion->status) === $status)>
                                    {{ App\Models\AcceptanceCriterion::STATUS_LABELS[$status] }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('status')" class="mt-2" />
                    </div>

                    <div class="flex items-center justify-end mt-4">
                        <x-primary-button>{{ __('Save') }}</x-primary-button>
                    </div>
                </form>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-2">{{ __('AI Assistance') }}</h3>
                <p class="text-sm text-gray-600 mb-2">
                    {{ __('Request an AI-improved rewrite of the description. This runs in the background and the page updates automatically when the suggestion is ready.') }}
                </p>

                <div class="divide-y divide-gray-200">
                    <x-ai-assistance-panel field="description" :label="__('Description')" :model="$acceptanceCriterion" :ai-request="$latestAiRequests->get('description')" />
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900">{{ __('Delete Acceptance Criterion') }}</h3>
                <p class="mt-1 text-sm text-gray-600">{{ __('This cannot be undone.') }}</p>

                <x-danger-button x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-acceptance-criterion-deletion')" class="mt-4">
                    {{ __('Delete Acceptance Criterion') }}
                </x-danger-button>
            </div>
        </div>
    </div>

    <x-modal name="confirm-acceptance-criterion-deletion" focusable>
        <form method="POST" action="{{ route('acceptance-criteria.destroy', $acceptanceCriterion) }}" class="p-6">
            @csrf
            @method('DELETE')

            <h2 class="text-lg font-medium text-gray-900">
                {{ __('Delete this acceptance criterion?') }}
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                {{ __('This cannot be undone.') }}
            </p>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button type="button" x-on:click="$dispatch('close')">
                    {{ __('Cancel') }}
                </x-secondary-button>
                <x-danger-button type="submit">
                    {{ __('Delete Acceptance Criterion') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</x-app-layout>
