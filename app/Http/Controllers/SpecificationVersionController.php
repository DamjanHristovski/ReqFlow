<?php

namespace App\Http\Controllers;

use App\Models\Specification;
use App\Models\SpecificationVersion;
use App\Models\UserStory;
use App\Services\SpecificationVersionService;
use Illuminate\Http\Request;

class SpecificationVersionController extends Controller
{
    public function index(Specification $specification)
    {
        return $this->indexFor($specification, 'specifications.versions.index');
    }

    public function indexForUserStory(UserStory $userStory)
    {
        return $this->indexFor($userStory, 'user-stories.versions.index');
    }

    public function show(Specification $specification, SpecificationVersion $version)
    {
        return $this->showFor($specification, $version, 'specification_id', 'specifications.versions.show');
    }

    public function showForUserStory(UserStory $userStory, SpecificationVersion $version)
    {
        return $this->showFor($userStory, $version, 'user_story_id', 'user-stories.versions.show');
    }

    public function compare(Request $request, Specification $specification)
    {
        return $this->compareFor($request, $specification, 'specifications.versions.compare');
    }

    public function compareForUserStory(Request $request, UserStory $userStory)
    {
        return $this->compareFor($request, $userStory, 'user-stories.versions.compare');
    }

    public function restore(Specification $specification, SpecificationVersion $version, SpecificationVersionService $versions)
    {
        return $this->restoreFor($specification, $version, $versions, 'specification_id', 'specifications.show');
    }

    public function restoreForUserStory(UserStory $userStory, SpecificationVersion $version, SpecificationVersionService $versions)
    {
        return $this->restoreFor($userStory, $version, $versions, 'user_story_id', 'user-stories.show');
    }

    private function indexFor(Specification|UserStory $subject, string $view)
    {
        $this->authorize('view', $subject);

        $versions = $subject->versions()->orderByDesc('version_number')->get();

        $subjectVariable = $subject instanceof Specification ? 'specification' : 'userStory';

        return view($view, [$subjectVariable => $subject, 'versions' => $versions]);
    }

    private function showFor(Specification|UserStory $subject, SpecificationVersion $version, string $column, string $view)
    {
        $this->authorize('view', $subject);
        abort_unless($version->{$column} === $subject->id, 404);

        $subjectVariable = $subject instanceof Specification ? 'specification' : 'userStory';

        return view($view, [$subjectVariable => $subject, 'version' => $version]);
    }

    private function compareFor(Request $request, Specification|UserStory $subject, string $view)
    {
        $this->authorize('view', $subject);

        $validated = $request->validate([
            'from' => ['required', 'integer'],
            'to' => ['required', 'integer'],
        ]);

        $from = $subject->versions()->where('version_number', $validated['from'])->firstOrFail();
        $to = $subject->versions()->where('version_number', $validated['to'])->firstOrFail();

        $subjectVariable = $subject instanceof Specification ? 'specification' : 'userStory';

        return view($view, [$subjectVariable => $subject, 'from' => $from, 'to' => $to]);
    }

    private function restoreFor(Specification|UserStory $subject, SpecificationVersion $version, SpecificationVersionService $versions, string $column, string $redirectRoute)
    {
        $this->authorize('update', $subject);
        abort_unless($version->{$column} === $subject->id, 404);

        $versions->restore($version);

        return redirect()->route($redirectRoute, $subject)
            ->with('status', "Restored to version {$version->version_number}.");
    }
}
