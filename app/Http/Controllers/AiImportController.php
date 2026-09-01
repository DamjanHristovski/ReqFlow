<?php

namespace App\Http\Controllers;

use App\Jobs\ImportPdfJob;
use App\Models\AiRequest;
use App\Models\Project;
use App\Models\Specification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AiImportController extends Controller
{
    /**
     * Accept a PDF and queue an import: a triage job extracts a specification
     * and/or user stories from it (whichever the document contains).
     */
    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('create', [Specification::class, $project]);
        abort_unless($request->user()->hasAiConfigured(), 403);

        $request->validate([
            'document' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ]);

        $path = $request->file('document')->store('ai-imports', 'local');

        $aiRequest = $project->aiRequests()->create([
            'user_id' => Auth::id(),
            'type' => AiRequest::TYPE_IMPORT_PDF,
            'status' => AiRequest::STATUS_PENDING,
            'prompt' => $path,
        ]);

        ImportPdfJob::dispatch($aiRequest);

        return redirect()->route('projects.show', $project)
            ->with('status', 'PDF import queued — the specification and any user stories will appear once it\'s processed.');
    }
}
