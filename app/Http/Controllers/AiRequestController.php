<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateAcceptanceCriteriaJob;
use App\Jobs\GenerateNextStepsJob;
use App\Jobs\GenerateUserStoriesJob;
use App\Jobs\ImproveTextJob;
use App\Models\AcceptanceCriterion;
use App\Models\AiRequest;
use App\Models\Specification;
use App\Models\UserStory;
use App\Services\SpecificationVersionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AiRequestController extends Controller
{
    public function improveText(Request $request, Specification $specification)
    {
        return $this->requestImprovement($request, $specification);
    }

    public function improveTextForUserStory(Request $request, UserStory $userStory)
    {
        return $this->requestImprovement($request, $userStory);
    }

    public function improveTextForAcceptanceCriterion(Request $request, AcceptanceCriterion $acceptanceCriterion)
    {
        return $this->requestImprovement($request, $acceptanceCriterion);
    }

    public function generateNextStepsForSpecification(Specification $specification)
    {
        return $this->requestNextSteps($specification);
    }

    public function generateNextStepsForUserStory(UserStory $userStory)
    {
        return $this->requestNextSteps($userStory);
    }

    public function generateUserStories(Specification $specification)
    {
        $this->authorize('create', [UserStory::class, $specification->project]);

        if ($guard = $this->requireAiKey()) {
            return $guard;
        }

        $aiRequest = $specification->aiRequests()->create([
            'user_id' => Auth::id(),
            'type' => AiRequest::TYPE_GENERATE_USER_STORIES,
            'status' => AiRequest::STATUS_PENDING,
            'prompt' => 'Generate user stories for Specification #'.$specification->id,
        ]);

        GenerateUserStoriesJob::dispatch($aiRequest);

        return redirect()->route('specifications.show', $specification)
            ->with('status', 'User stories requested — they\'ll appear in this project once generated.');
    }

    public function generateAcceptanceCriteria(UserStory $userStory)
    {
        $this->authorize('create', [AcceptanceCriterion::class, $userStory]);

        if ($guard = $this->requireAiKey()) {
            return $guard;
        }

        $aiRequest = $userStory->aiRequests()->create([
            'user_id' => Auth::id(),
            'type' => AiRequest::TYPE_GENERATE_ACCEPTANCE_CRITERIA,
            'status' => AiRequest::STATUS_PENDING,
            'prompt' => 'Generate acceptance criteria for User Story #'.$userStory->id,
        ]);

        GenerateAcceptanceCriteriaJob::dispatch($aiRequest);

        return redirect()->route('user-stories.show', $userStory)
            ->with('status', 'Acceptance criteria requested — they\'ll appear once generated.');
    }

    /**
     * Lightweight polling endpoint: returns just the request's status as JSON so
     * a pending panel can reload the page the moment the job finishes.
     */
    public function status(AiRequest $aiRequest)
    {
        $context = $aiRequest->subject() ?? $aiRequest->project;

        if ($context) {
            $this->authorize('view', $context);
        } else {
            abort_unless($aiRequest->user_id === Auth::id(), 403);
        }

        return response()->json([
            'status' => $aiRequest->status,
            'done' => $aiRequest->isCompleted() || $aiRequest->isFailed(),
        ]);
    }

    public function apply(AiRequest $aiRequest, SpecificationVersionService $versions)
    {
        $subject = $aiRequest->subject();

        $this->authorize('update', $subject);
        abort_unless($aiRequest->type === AiRequest::TYPE_IMPROVE_TEXT && $aiRequest->isCompleted(), 404);

        $isVersioned = $subject instanceof Specification || $subject instanceof UserStory;
        $originalContent = $isVersioned ? $versions->snapshot($subject) : null;

        $subject->update([$aiRequest->field => $aiRequest->response]);

        if ($isVersioned) {
            $versions->recordVersionIfChanged($subject, $originalContent, Auth::user());
        }

        return redirect()->route($this->editRouteFor($subject), $subject)->with('status', 'AI suggestion applied.');
    }

    private function requestImprovement(Request $request, Specification|UserStory|AcceptanceCriterion $subject)
    {
        $this->authorize('update', $subject);

        if ($guard = $this->requireAiKey()) {
            return $guard;
        }

        $validated = $request->validate([
            'field' => ['required', 'string', Rule::in($subject::IMPROVABLE_FIELDS)],
        ]);

        $field = $validated['field'];
        $content = $subject->{$field};

        if (blank($content)) {
            return back()->with('status', 'There\'s no text in that field to improve yet.');
        }

        $aiRequest = $subject->aiRequests()->create([
            'user_id' => Auth::id(),
            'type' => AiRequest::TYPE_IMPROVE_TEXT,
            'field' => $field,
            'status' => AiRequest::STATUS_PENDING,
            'prompt' => $content,
        ]);

        ImproveTextJob::dispatch($aiRequest);

        return redirect()->route($this->editRouteFor($subject), $subject)
            ->with('status', 'AI improvement requested — this section will update once it\'s ready.');
    }

    private function requestNextSteps(Specification|UserStory $subject)
    {
        $this->authorize('update', $subject);

        if ($guard = $this->requireAiKey()) {
            return $guard;
        }

        $aiRequest = $subject->aiRequests()->create([
            'user_id' => Auth::id(),
            'type' => AiRequest::TYPE_GENERATE_NEXT_STEPS,
            'status' => AiRequest::STATUS_PENDING,
            'prompt' => 'Generate next steps for '.class_basename($subject)." #{$subject->id}",
        ]);

        GenerateNextStepsJob::dispatch($aiRequest);

        return redirect()->route($this->showRouteFor($subject), $subject)
            ->with('status', 'Next steps requested — this section will update once it\'s ready.');
    }

    /**
     * Defense-in-depth: the UI disables AI controls without a key, but the
     * routes must refuse too. Returns a redirect to bail with, or null to go on.
     */
    private function requireAiKey(): ?RedirectResponse
    {
        if (Auth::user()->hasAiConfigured()) {
            return null;
        }

        return back()->with('status', 'Add an AI API key in your profile to use AI features.');
    }

    private function editRouteFor(Specification|UserStory|AcceptanceCriterion $subject): string
    {
        return match (true) {
            $subject instanceof Specification => 'specifications.edit',
            $subject instanceof UserStory => 'user-stories.edit',
            $subject instanceof AcceptanceCriterion => 'acceptance-criteria.edit',
        };
    }

    private function showRouteFor(Specification|UserStory $subject): string
    {
        return match (true) {
            $subject instanceof Specification => 'specifications.show',
            $subject instanceof UserStory => 'user-stories.show',
        };
    }
}
