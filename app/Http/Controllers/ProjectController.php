<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\AiRequest;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;

class ProjectController extends Controller
{
    public function index(Team $team)
    {
        $this->authorize('view', $team);

        $projects = $team->projects()->latest()->get();

        return view('projects.index', compact('team', 'projects'));
    }

    public function create(Team $team)
    {
        $this->authorize('create', [Project::class, $team]);

        return view('projects.create', compact('team'));
    }

    public function store(StoreProjectRequest $request, Team $team)
    {
        $project = $team->projects()->create([
            ...$request->validated(),
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('projects.show', $project)->with('status', 'Project created.');
    }

    public function show(Project $project)
    {
        $this->authorize('view', $project);

        $project->load('specifications', 'userStories');

        $latestImport = $project->aiRequests()
            ->where('type', AiRequest::TYPE_IMPORT_PDF)
            ->latest()
            ->first();

        return view('projects.show', compact('project', 'latestImport'));
    }

    public function edit(Project $project)
    {
        $this->authorize('update', $project);

        return view('projects.edit', compact('project'));
    }

    public function update(UpdateProjectRequest $request, Project $project)
    {
        $project->update($request->validated());

        return redirect()->route('projects.show', $project)->with('status', 'Project updated.');
    }

    public function destroy(Project $project)
    {
        $this->authorize('delete', $project);

        $team = $project->team;
        $project->delete();

        return redirect()->route('teams.projects.index', $team)->with('status', 'Project deleted.');
    }
}
