<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Version') }} {{ $version->version_number }} &mdash; {{ $specification->title }}
                </h2>
                <a href="{{ route('specifications.versions.index', $specification) }}" class="text-sm text-gray-500 hover:underline">
                    &larr; {{ __('Version history') }}
                </a>
            </div>
            @if ($version->version_number !== $specification->current_version)
                @can('update', $specification)
                    <x-secondary-button x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-version-restore')">
                        {{ __('Restore this version') }}
                    </x-secondary-button>
                @endcan
            @endif
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 space-y-6">
                <div class="text-sm text-gray-500">
                    {{ __('Changed by') }} {{ $version->changedBy?->name ?? __('Unknown') }}
                    &middot; {{ $version->created_at->format('M j, Y g:ia') }}
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">{{ __('Title') }}</h3>
                    <p class="mt-1 text-gray-900">{{ $version->content['title'] ?? '—' }}</p>
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">{{ __('Description') }}</h3>
                    <p class="mt-1 text-gray-900 whitespace-pre-line">{{ $version->content['description'] ?? '—' }}</p>
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">{{ __('Goals') }}</h3>
                    <p class="mt-1 text-gray-900 whitespace-pre-line">{{ $version->content['goals'] ?? '—' }}</p>
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">{{ __('Scope') }}</h3>
                    <p class="mt-1 text-gray-900 whitespace-pre-line">{{ $version->content['scope'] ?? '—' }}</p>
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">{{ __('Functional Requirements') }}</h3>
                    <p class="mt-1 text-gray-900 whitespace-pre-line">{{ $version->content['functional_requirements'] ?? '—' }}</p>
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">{{ __('Non-Functional Requirements') }}</h3>
                    <p class="mt-1 text-gray-900 whitespace-pre-line">{{ $version->content['non_functional_requirements'] ?? '—' }}</p>
                </div>
            </div>
        </div>
    </div>

    @if ($version->version_number !== $specification->current_version)
        @can('update', $specification)
            <x-modal name="confirm-version-restore" focusable>
                <form method="POST" action="{{ route('specifications.versions.restore', [$specification, $version]) }}" class="p-6">
                    @csrf

                    <h2 class="text-lg font-medium text-gray-900">
                        {{ __('Restore Version :version?', ['version' => $version->version_number]) }}
                    </h2>

                    <p class="mt-1 text-sm text-gray-600">
                        {{ __('This rewinds the specification back to this version\'s content. Nothing is deleted — the full version history stays intact.') }}
                    </p>

                    <div class="mt-6 flex justify-end gap-3">
                        <x-secondary-button type="button" x-on:click="$dispatch('close')">
                            {{ __('Cancel') }}
                        </x-secondary-button>
                        <x-primary-button type="submit">
                            {{ __('Restore') }}
                        </x-primary-button>
                    </div>
                </form>
            </x-modal>
        @endcan
    @endif
</x-app-layout>
