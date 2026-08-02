<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTeamRequest;
use App\Http\Requests\UpdateTeamRequest;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TeamController extends Controller
{
    public function index()
    {
        $teams = Auth::user()->teams()->latest()->get();

        return view('teams.index', compact('teams'));
    }

    public function create()
    {
        $this->authorize('create', Team::class);

        return view('teams.create');
    }

    public function store(StoreTeamRequest $request)
    {
        $team = DB::transaction(function () use ($request) {
            $team = Team::create([
                'name' => $request->validated('name'),
                'created_by' => Auth::id(),
            ]);

            $team->teamMembers()->create([
                'user_id' => Auth::id(),
                'role' => TeamMember::ROLE_OWNER,
            ]);

            return $team;
        });

        return redirect()->route('teams.show', $team)->with('status', 'Team created.');
    }

    public function show(Team $team)
    {
        $this->authorize('view', $team);

        $team->load('members');

        return view('teams.show', compact('team'));
    }

    public function edit(Team $team)
    {
        $this->authorize('update', $team);

        return view('teams.edit', compact('team'));
    }

    public function update(UpdateTeamRequest $request, Team $team)
    {
        $team->update($request->validated());

        return redirect()->route('teams.show', $team)->with('status', 'Team updated.');
    }

    public function destroy(Team $team)
    {
        $this->authorize('delete', $team);

        $team->delete();

        return redirect()->route('teams.index')->with('status', 'Team deleted.');
    }
}
