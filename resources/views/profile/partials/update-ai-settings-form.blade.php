<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('AI Settings') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Add your own OpenAI or Google Gemini API key to enable the AI features (improving text, generating user stories, importing from PDF). Your key is stored encrypted and never shown again after saving.') }}
        </p>
    </header>

    @if ($user->hasAiConfigured())
        <div class="mt-6 space-y-4">
            <div>
                <span class="block text-sm font-medium text-gray-700">{{ __('Provider') }}</span>
                <p class="mt-1 text-gray-900">{{ config('ai.providers.'.$user->ai_provider.'.label', $user->ai_provider) }}</p>
            </div>

            <div>
                <span class="block text-sm font-medium text-gray-700">{{ __('API key') }}</span>
                <p class="mt-1 font-mono text-gray-900">{{ $user->maskedAiKey() }}</p>
            </div>

            <form method="post" action="{{ route('ai-settings.destroy') }}">
                @csrf
                @method('delete')

                <div class="flex items-center gap-4">
                    <x-danger-button>{{ __('Remove Key') }}</x-danger-button>

                    @if (session('status') === 'ai-settings-updated')
                        <p
                            x-data="{ show: true }"
                            x-show="show"
                            x-transition
                            x-init="setTimeout(() => show = false, 2000)"
                            class="text-sm text-gray-600"
                        >{{ __('Saved.') }}</p>
                    @endif
                </div>
            </form>
        </div>
    @else
        <form method="post" action="{{ route('ai-settings.update') }}" class="mt-6 space-y-6">
            @csrf
            @method('patch')

            <div>
                <x-input-label for="ai_provider" :value="__('Provider')" />
                <select
                    id="ai_provider"
                    name="ai_provider"
                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                >
                    @foreach (config('ai.providers') as $key => $provider)
                        <option value="{{ $key }}" @selected(old('ai_provider') === $key)>{{ $provider['label'] }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->updateAiSettings->get('ai_provider')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="ai_api_key" :value="__('API Key')" />
                <x-text-input id="ai_api_key" name="ai_api_key" type="password" class="mt-1 block w-full" autocomplete="off" placeholder="sk-…" />
                <x-input-error :messages="$errors->updateAiSettings->get('ai_api_key')" class="mt-2" />
            </div>

            <div class="flex items-center gap-4">
                <x-primary-button>{{ __('Save Key') }}</x-primary-button>

                @if (session('status') === 'ai-settings-removed')
                    <p
                        x-data="{ show: true }"
                        x-show="show"
                        x-transition
                        x-init="setTimeout(() => show = false, 2000)"
                        class="text-sm text-gray-600"
                    >{{ __('Removed.') }}</p>
                @endif
            </div>
        </form>
    @endif
</section>
