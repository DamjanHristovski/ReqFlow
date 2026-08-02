<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Project;
use App\Models\Specification;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $teamIds = $user->teams()->pluck('teams.id');

        $teams = $user->teams()->withCount('projects')->latest()->get();

        $recentProjects = Project::whereIn('team_id', $teamIds)
            ->with('team')
            ->latest('updated_at')
            ->take(5)
            ->get();

        $recentSpecifications = Specification::whereHas(
            'project', fn ($query) => $query->whereIn('team_id', $teamIds)
        )
            ->with('project')
            ->latest('updated_at')
            ->take(5)
            ->get();

        $recentComments = Comment::whereHas(
            'specification.project', fn ($query) => $query->whereIn('team_id', $teamIds)
        )
            ->with(['user', 'specification'])
            ->latest()
            ->take(5)
            ->get();

        return view('dashboard', compact(
            'teams', 'recentProjects', 'recentSpecifications', 'recentComments'
        ));
    }
}
