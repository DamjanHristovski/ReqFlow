<?php

use App\Http\Controllers\AiRequestController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SpecificationController;
use App\Http\Controllers\SpecificationVersionController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TeamMemberController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(Auth::guard()->check() ? 'dashboard' : 'login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('teams', TeamController::class);

    Route::post('teams/{team}/members', [TeamMemberController::class, 'store'])->name('teams.members.store');
    Route::patch('teams/{team}/members/{member}', [TeamMemberController::class, 'update'])->name('teams.members.update');
    Route::delete('teams/{team}/members/{member}', [TeamMemberController::class, 'destroy'])->name('teams.members.destroy');

    Route::resource('teams.projects', ProjectController::class)->shallow();
    Route::resource('projects.specifications', SpecificationController::class)->shallow()->except(['index']);

    Route::get('specifications/{specification}/versions', [SpecificationVersionController::class, 'index'])->name('specifications.versions.index');
    Route::get('specifications/{specification}/versions/compare', [SpecificationVersionController::class, 'compare'])->name('specifications.versions.compare');
    Route::get('specifications/{specification}/versions/{version}', [SpecificationVersionController::class, 'show'])->name('specifications.versions.show');
    Route::post('specifications/{specification}/versions/{version}/restore', [SpecificationVersionController::class, 'restore'])->name('specifications.versions.restore');

    Route::post('specifications/{specification}/comments', [CommentController::class, 'store'])->name('comments.store');
    Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

    Route::post('specifications/{specification}/ai/improve-text', [AiRequestController::class, 'improveText'])->name('ai.improve-text');
    Route::post('specifications/{specification}/ai/generate-next-steps', [AiRequestController::class, 'generateNextSteps'])->name('ai.generate-next-steps');
    Route::post('ai-requests/{aiRequest}/apply', [AiRequestController::class, 'apply'])->name('ai-requests.apply');
});

require __DIR__.'/auth.php';
