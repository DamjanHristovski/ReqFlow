<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSpecificationRequest;
use App\Http\Requests\UpdateSpecificationRequest;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Specification;
use App\Services\SpecificationVersionService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class SpecificationController extends Controller
{
    public function create(Project $project)
    {
        $this->authorize('create', [Specification::class, $project]);

        return view('specifications.create', compact('project'));
    }

    public function store(StoreSpecificationRequest $request, Project $project, SpecificationVersionService $versions)
    {
        $specification = $project->specifications()->create([
            ...$request->validated(),
            'created_by' => Auth::id(),
        ]);

        $versions->recordInitialVersion($specification, Auth::user());

        return redirect()->route('specifications.show', $specification)->with('status', 'Specification created.');
    }

    public function show(Specification $specification)
    {
        $this->authorize('view', $specification);

        $allComments = $specification->comments()->with('user')->get();
        $comments = Comment::buildTree($allComments);

        return view('specifications.show', compact('specification', 'comments', 'allComments'));
    }

    public function edit(Specification $specification)
    {
        $this->authorize('update', $specification);

        return view('specifications.edit', compact('specification'));
    }

    public function update(UpdateSpecificationRequest $request, Specification $specification, SpecificationVersionService $versions)
    {
        $originalContent = $versions->snapshot($specification);
        $newContent = Arr::only($request->validated(), Specification::VERSIONED_FIELDS);

        if ($newContent !== $originalContent && ! $request->boolean('force_new_version')) {
            $match = $versions->findMatchingVersion($specification, $newContent);

            if ($match) {
                return back()->withInput()->with([
                    'matched_version_id' => $match->id,
                    'matched_version_number' => $match->version_number,
                ]);
            }
        }

        $specification->update($request->validated());

        $versions->recordVersionIfChanged($specification, $originalContent, Auth::user());

        return redirect()->route('specifications.show', $specification)->with('status', 'Specification updated.');
    }

    public function destroy(Specification $specification)
    {
        $this->authorize('delete', $specification);

        $project = $specification->project;
        $specification->delete();

        return redirect()->route('projects.show', $project)->with('status', 'Specification deleted.');
    }
}
