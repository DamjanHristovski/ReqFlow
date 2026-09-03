<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAiSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AiSettingsController extends Controller
{
    /**
     * Save (or replace) the user's AI provider + API key. The key is stored
     * encrypted via the User model's 'encrypted' cast.
     */
    public function update(UpdateAiSettingsRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated())->save();

        return redirect()->route('profile.edit')->with('status', 'ai-settings-updated');
    }

    /**
     * Remove the stored key (the "Remove" button), disabling AI features again.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->forceFill([
            'ai_provider' => null,
            'ai_api_key' => null,
        ])->save();

        return redirect()->route('profile.edit')->with('status', 'ai-settings-removed');
    }
}
