<?php

namespace App\Http\Controllers;

use App\Models\Specification;
use App\Models\SpecificationVersion;
use App\Services\SpecificationVersionService;
use Illuminate\Http\Request;

class SpecificationVersionController extends Controller
{
    public function index(Specification $specification)
    {
        $this->authorize('view', $specification);

        $versions = $specification->versions()->orderByDesc('version_number')->get();

        return view('specifications.versions.index', compact('specification', 'versions'));
    }

    public function show(Specification $specification, SpecificationVersion $version)
    {
        $this->authorize('view', $specification);
        abort_unless($version->specification_id === $specification->id, 404);

        return view('specifications.versions.show', compact('specification', 'version'));
    }

    public function compare(Request $request, Specification $specification)
    {
        $this->authorize('view', $specification);

        $validated = $request->validate([
            'from' => ['required', 'integer'],
            'to' => ['required', 'integer'],
        ]);

        $from = $specification->versions()->where('version_number', $validated['from'])->firstOrFail();
        $to = $specification->versions()->where('version_number', $validated['to'])->firstOrFail();

        return view('specifications.versions.compare', compact('specification', 'from', 'to'));
    }

    public function restore(Specification $specification, SpecificationVersion $version, SpecificationVersionService $versions)
    {
        $this->authorize('update', $specification);
        abort_unless($version->specification_id === $specification->id, 404);

        $versions->restore($version);

        return redirect()->route('specifications.show', $specification)
            ->with('status', "Restored to version {$version->version_number}.");
    }
}
