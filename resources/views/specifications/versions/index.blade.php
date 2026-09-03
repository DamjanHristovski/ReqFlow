<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Version History') }} &mdash; {{ $specification->title }}
            </h2>
            <a href="{{ route('specifications.show', $specification) }}" class="text-sm text-gray-500 hover:underline">
                &larr; {{ __('Back to specification') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="p-4 bg-green-100 text-green-800 rounded-md">
                    {{ session('status') }}
                </div>
            @endif

            @if ($versions->count() >= 2)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __('Compare Versions') }}</h3>
                    <form method="GET" action="{{ route('specifications.versions.compare', $specification) }}" class="flex items-end gap-4">
                        <div>
                            <x-input-label for="from" :value="__('From')" />
                            <select id="from" name="from" class="mt-1 rounded-md border-gray-300 shadow-sm">
                                @foreach ($versions as $version)
                                    <option value="{{ $version->version_number }}" @selected($loop->last)>v{{ $version->version_number }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="to" :value="__('To')" />
                            <select id="to" name="to" class="mt-1 rounded-md border-gray-300 shadow-sm">
                                @foreach ($versions as $version)
                                    <option value="{{ $version->version_number }}" @selected($loop->first)>v{{ $version->version_number }}</option>
                                @endforeach
                            </select>
                        </div>
                        <x-primary-button>{{ __('Compare') }}</x-primary-button>
                    </form>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <ul class="divide-y divide-gray-200">
                    @foreach ($versions as $version)
                        <li class="p-6 flex justify-between items-center">
                            <div>
                                <a href="{{ route('specifications.versions.show', [$specification, $version]) }}" class="font-medium text-gray-900 hover:underline">
                                    {{ __('Version') }} {{ $version->version_number }}
                                </a>
                                @if ($version->version_number === $specification->current_version)
                                    <span class="ms-2 text-xs uppercase tracking-wide text-green-700">{{ __('current') }}</span>
                                @endif
                                <div class="text-sm text-gray-500">
                                    {{ __('by') }} {{ $version->changedBy?->name ?? __('Unknown') }}
                                    &middot; {{ $version->created_at->format('M j, Y g:ia') }}
                                </div>
                            </div>

                            @if ($version->version_number !== $specification->current_version)
                                @can('update', $specification)
                                    <x-secondary-button x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-version-restore-{{ $version->id }}')">
                                        {{ __('Restore') }}
                                    </x-secondary-button>
                                @endcan
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>

    @foreach ($versions as $version)
        @if ($version->version_number !== $specification->current_version)
            @can('update', $specification)
                <x-modal name="confirm-version-restore-{{ $version->id }}" focusable>
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
    @endforeach
</x-app-layout>
