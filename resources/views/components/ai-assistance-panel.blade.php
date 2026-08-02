@props(['field', 'label', 'specification', 'aiRequest' => null])

<div class="py-4 first:pt-0 last:pb-0">
    <div class="flex items-center justify-between">
        <span class="text-sm font-medium text-gray-700">{{ $label }}</span>
        <form method="POST" action="{{ route('ai.improve-text', $specification) }}">
            @csrf
            <input type="hidden" name="field" value="{{ $field }}">
            <x-secondary-button type="submit">{{ __('Improve') }}</x-secondary-button>
        </form>
    </div>

    @if ($aiRequest)
        <div class="mt-2 p-3 rounded-md {{ $aiRequest->isFailed() ? 'bg-red-50' : 'bg-indigo-50' }}">
            @if ($aiRequest->isPending() || $aiRequest->isProcessing())
                <p class="text-sm text-gray-600">{{ __('AI is working on this — refresh in a moment.') }}</p>
            @elseif ($aiRequest->isFailed())
                <p class="text-sm text-red-700">{{ __('AI request failed: :error', ['error' => $aiRequest->error_message]) }}</p>
            @elseif ($aiRequest->isCompleted())
                <p class="text-xs font-semibold text-indigo-700 uppercase tracking-wide">{{ __('AI Suggestion') }}</p>
                <p class="mt-1 text-sm text-gray-900 whitespace-pre-line">{{ $aiRequest->response }}</p>
                <form method="POST" action="{{ route('ai-requests.apply', $aiRequest) }}" class="mt-2">
                    @csrf
                    <x-primary-button type="submit">{{ __('Apply') }}</x-primary-button>
                </form>
            @endif
        </div>
    @endif
</div>
