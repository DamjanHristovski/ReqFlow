<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateNextStepsJob;
use App\Jobs\ImproveSpecificationTextJob;
use App\Models\AiRequest;
use App\Models\Specification;
use App\Services\SpecificationVersionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AiRequestController extends Controller
{
    public function improveText(Request $request, Specification $specification)
    {
        $this->authorize('update', $specification);

        $validated = $request->validate([
            'field' => ['required', 'string', Rule::in(Specification::IMPROVABLE_FIELDS)],
        ]);

        $field = $validated['field'];
        $content = $specification->{$field};

        if (blank($content)) {
            return back()->with('status', 'There\'s no text in that field to improve yet.');
        }

        $aiRequest = $specification->aiRequests()->create([
            'user_id' => Auth::id(),
            'type' => AiRequest::TYPE_IMPROVE_TEXT,
            'field' => $field,
            'status' => AiRequest::STATUS_PENDING,
            'prompt' => $content,
        ]);

        ImproveSpecificationTextJob::dispatch($aiRequest);

        return redirect()->route('specifications.edit', $specification)
            ->with('status', 'AI improvement requested — this section will update once it\'s ready.');
    }

    public function generateNextSteps(Specification $specification)
    {
        $this->authorize('update', $specification);

        $aiRequest = $specification->aiRequests()->create([
            'user_id' => Auth::id(),
            'type' => AiRequest::TYPE_GENERATE_NEXT_STEPS,
            'status' => AiRequest::STATUS_PENDING,
            'prompt' => "Generate next steps for specification #{$specification->id}",
        ]);

        GenerateNextStepsJob::dispatch($aiRequest);

        return redirect()->route('specifications.show', $specification)
            ->with('status', 'Next steps requested — this section will update once it\'s ready.');
    }

    public function apply(AiRequest $aiRequest, SpecificationVersionService $versions)
    {
        $specification = $aiRequest->specification;

        $this->authorize('update', $specification);
        abort_unless($aiRequest->type === AiRequest::TYPE_IMPROVE_TEXT && $aiRequest->isCompleted(), 404);

        $originalContent = $versions->snapshot($specification);

        $specification->update([$aiRequest->field => $aiRequest->response]);

        $versions->recordVersionIfChanged($specification, $originalContent, Auth::user());

        return redirect()->route('specifications.edit', $specification)->with('status', 'AI suggestion applied.');
    }
}
