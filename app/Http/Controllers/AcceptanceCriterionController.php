<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAcceptanceCriterionRequest;
use App\Http\Requests\UpdateAcceptanceCriterionRequest;
use App\Models\AcceptanceCriterion;
use App\Models\AiRequest;
use App\Models\Comment;
use App\Models\UserStory;
use Illuminate\Support\Facades\Auth;

class AcceptanceCriterionController extends Controller
{
    public function create(UserStory $userStory)
    {
        $this->authorize('create', [AcceptanceCriterion::class, $userStory]);

        return view('acceptance-criteria.create', compact('userStory'));
    }

    public function store(StoreAcceptanceCriterionRequest $request, UserStory $userStory)
    {
        $acceptanceCriterion = $userStory->acceptanceCriteria()->create([
            ...$request->validated(),
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('acceptance-criteria.show', $acceptanceCriterion)->with('status', 'Acceptance criterion created.');
    }

    public function show(AcceptanceCriterion $acceptanceCriterion)
    {
        $this->authorize('view', $acceptanceCriterion);

        $allComments = $acceptanceCriterion->comments()->with('user')->get();
        $comments = Comment::buildTree($allComments);

        return view('acceptance-criteria.show', compact('acceptanceCriterion', 'comments', 'allComments'));
    }

    public function edit(AcceptanceCriterion $acceptanceCriterion)
    {
        $this->authorize('update', $acceptanceCriterion);

        $latestAiRequests = $acceptanceCriterion->aiRequests()
            ->where('type', AiRequest::TYPE_IMPROVE_TEXT)
            ->latest()
            ->get()
            ->unique('field')
            ->keyBy('field');

        return view('acceptance-criteria.edit', compact('acceptanceCriterion', 'latestAiRequests'));
    }

    public function update(UpdateAcceptanceCriterionRequest $request, AcceptanceCriterion $acceptanceCriterion)
    {
        $acceptanceCriterion->update($request->validated());

        return redirect()->route('acceptance-criteria.show', $acceptanceCriterion)->with('status', 'Acceptance criterion updated.');
    }

    public function destroy(AcceptanceCriterion $acceptanceCriterion)
    {
        $this->authorize('delete', $acceptanceCriterion);

        $userStory = $acceptanceCriterion->userStory;
        $acceptanceCriterion->delete();

        return redirect()->route('user-stories.show', $userStory)->with('status', 'Acceptance criterion deleted.');
    }
}
