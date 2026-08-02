<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSpecificationRequest;
use App\Http\Requests\UpdateSpecificationRequest;
use App\Models\Project;
use App\Models\Specification;
use Illuminate\Support\Facades\Auth;

class SpecificationController extends Controller
{
    public function create(Project $project)
    {
        $this->authorize('create', [Specification::class, $project]);

        return view('specifications.create', compact('project'));
    }

    public function store(StoreSpecificationRequest $request, Project $project)
    {
        $specification = $project->specifications()->create([
            ...$request->validated(),
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('specifications.show', $specification)->with('status', 'Specification created.');
    }

    public function show(Specification $specification)
    {
        $this->authorize('view', $specification);

        return view('specifications.show', compact('specification'));
    }

    public function edit(Specification $specification)
    {
        $this->authorize('update', $specification);

        return view('specifications.edit', compact('specification'));
    }

    public function update(UpdateSpecificationRequest $request, Specification $specification)
    {
        $specification->update($request->validated());

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
