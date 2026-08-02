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

                    <div class="mt-4">
                        <x-input-label for="description" :value="__('Description')" />
                        <textarea id="description" name="description" rows="3" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $specification->description) }}</textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="goals" :value="__('Goals')" />
                        <textarea id="goals" name="goals" rows="3" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('goals', $specification->goals) }}</textarea>
                        <x-input-error :messages="$errors->get('goals')" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="scope" :value="__('Scope')" />
                        <textarea id="scope" name="scope" rows="3" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('scope', $specification->scope) }}</textarea>
                        <x-input-error :messages="$errors->get('scope')" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="functional_requirements" :value="__('Functional Requirements')" />
                        <textarea id="functional_requirements" name="functional_requirements" rows="4" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('functional_requirements', $specification->functional_requirements) }}</textarea>
                        <x-input-error :messages="$errors->get('functional_requirements')" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="non_functional_requirements" :value="__('Non-Functional Requirements')" />
                        <textarea id="non_functional_requirements" name="non_functional_requirements" rows="4" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('non_functional_requirements', $specification->non_functional_requirements) }}</textarea>
                        <x-input-error :messages="$errors->get('non_functional_requirements')" class="mt-2" />
                    </div>

                    <div class="flex items-center justify-end mt-4">
                        <x-primary-button>{{ __('Save') }}</x-primary-button>
                    </div>
                </form>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900">{{ __('Delete Specification') }}</h3>
                <p class="mt-1 text-sm text-gray-600">{{ __('This cannot be undone.') }}</p>

                <form method="POST" action="{{ route('specifications.destroy', $specification) }}" onsubmit="return confirm('{{ __('Are you sure?') }}')" class="mt-4">
                    @csrf
                    @method('DELETE')
                    <x-danger-button>{{ __('Delete Specification') }}</x-danger-button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
