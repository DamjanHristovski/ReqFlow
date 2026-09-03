<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Compare') }} v{{ $from->version_number }} &rarr; v{{ $to->version_number }}
            </h2>
            <a href="{{ route('user-stories.versions.index', $userStory) }}" class="text-sm text-gray-500 hover:underline">
                &larr; {{ __('Version history') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                @php
                    $diffs = collect(App\Models\UserStory::VERSIONED_FIELDS)->mapWithKeys(
                        fn ($field) => [$field => \App\Support\TextDiffer::diffToHtml(
                            $from->content[$field] ?? null,
                            $to->content[$field] ?? null,
                        )]
                    );
                @endphp
                <table class="w-full table-fixed">
                    <thead>
                        <tr class="text-left text-sm font-semibold text-gray-700 uppercase tracking-wide">
                            <th class="w-1/6 pb-2">{{ __('Field') }}</th>
                            <th class="w-5/12 pb-2">{{ __('Version') }} {{ $from->version_number }}</th>
                            <th class="w-5/12 pb-2">{{ __('Version') }} {{ $to->version_number }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach (App\Models\UserStory::VERSIONED_FIELDS as $field)
                            <tr class="align-top">
                                <td class="py-3 pr-4 text-sm font-medium text-gray-700 align-middle">{{ \Illuminate\Support\Str::headline($field) }}</td>
                                <td class="py-3 pr-4 text-gray-900 whitespace-pre-line">
                                    {!! $diffs[$field]['from'] !== '' ? $diffs[$field]['from'] : '&mdash;' !!}
                                </td>
                                <td class="py-3 text-gray-900 whitespace-pre-line">
                                    {!! $diffs[$field]['to'] !== '' ? $diffs[$field]['to'] : '&mdash;' !!}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
