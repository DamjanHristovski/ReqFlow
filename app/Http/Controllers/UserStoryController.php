<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserStoryRequest;
use App\Http\Requests\UpdateUserStoryRequest;
use App\Models\AiRequest;
use App\Models\Comment;
use App\Models\Project;
use App\Models\UserStory;
use App\Services\SpecificationVersionService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class UserStoryController extends Controller
{
    public function create(Project $project)
    {
        $this->authorize('create', [UserStory::class, $project]);

        return view('user-stories.create', compact('project'));
    }

    public function store(StoreUserStoryRequest $request, Project $project, SpecificationVersionService $versions)
    {
        $userStory = $project->userStories()->create([
            ...$request->validated(),
            'created_by' => Auth::id(),
        ]);

        $versions->recordInitialVersion($userStory, Auth::user());

        return redirect()->route('user-stories.show', $userStory)->with('status', 'User story created.');
    }

    public function show(UserStory $userStory)
    {
        $this->authorize('view', $userStory);

        $userStory->load('acceptanceCriteria');

        $allComments = $userStory->comments()->with('user')->get();
        $comments = Comment::buildTree($allComments);

        $latestNextSteps = $userStory->aiRequests()
            ->where('type', AiRequest::TYPE_GENERATE_NEXT_STEPS)
            ->latest()
            ->first();

        $latestCriteria = $userStory->aiRequests()
            ->where('type', AiRequest::TYPE_GENERATE_ACCEPTANCE_CRITERIA)
            ->latest()
            ->first();

        return view('user-stories.show', compact('userStory', 'comments', 'allComments', 'latestNextSteps', 'latestCriteria'));
    }

    public function edit(UserStory $userStory)
    {
        $this->authorize('update', $userStory);

        $latestAiRequests = $userStory->aiRequests()
            ->where('type', AiRequest::TYPE_IMPROVE_TEXT)
            ->latest()
            ->get()
            ->unique('field')
            ->keyBy('field');

        return view('user-stories.edit', compact('userStory', 'latestAiRequests'));
    }

    public function update(UpdateUserStoryRequest $request, UserStory $userStory, SpecificationVersionService $versions)
    {
        $originalContent = $versions->snapshot($userStory);
        $newContent = Arr::only($request->validated(), UserStory::VERSIONED_FIELDS);

        if ($newContent !== $originalContent && ! $request->boolean('force_new_version')) {
            $match = $versions->findMatchingVersion($userStory, $newContent);

            if ($match) {
                return back()->withInput()->with([
                    'matched_version_id' => $match->id,
                    'matched_version_number' => $match->version_number,
                ]);
            }
        }

        $userStory->update($request->validated());

        $versions->recordVersionIfChanged($userStory, $originalContent, Auth::user());

        return redirect()->route('user-stories.show', $userStory)->with('status', 'User story updated.');
    }

    public function destroy(UserStory $userStory)
    {
        $this->authorize('delete', $userStory);

        $project = $userStory->project;
        $userStory->delete();

        return redirect()->route('projects.show', $project)->with('status', 'User story deleted.');
    }
}
