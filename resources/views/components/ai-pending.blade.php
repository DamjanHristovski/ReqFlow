@props(['message' => 'Generating…', 'request' => null])

<div {{ $attributes->merge(['class' => 'mt-2 flex items-center gap-3 rounded-md bg-indigo-50 p-3']) }}>
    <svg class="h-5 w-5 animate-spin text-indigo-600 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
    </svg>
    <span class="text-sm text-indigo-700">{{ $message }}</span>
</div>

@if ($request)
    <script>
        (function () {
            var url = @json(route('ai-requests.status', $request));
            var tries = 0;
            function poll() {
                if (tries++ > 120) return; // give up after ~5 minutes
                fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (data) {
                        if (data && data.done) {
                            window.location.reload();
                        } else {
                            setTimeout(poll, 2500);
                        }
                    })
                    .catch(function () { setTimeout(poll, 4000); });
            }
            setTimeout(poll, 2000);
        })();
    </script>
@else
    <script>setTimeout(function () { window.location.reload(); }, 4000);</script>
@endif
