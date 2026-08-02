@props(['field', 'label', 'specification', 'rows' => 3])

<div class="mt-4">
    <x-input-label for="{{ $field }}" :value="$label" />
    <textarea id="{{ $field }}" name="{{ $field }}" rows="{{ $rows }}" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old($field, $specification->{$field}) }}</textarea>
    <x-input-error :messages="$errors->get($field)" class="mt-2" />
</div>
