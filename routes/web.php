<?php

use App\Http\Controllers\AcceptanceCriterionController;
use App\Http\Controllers\AiImportController;
use App\Http\Controllers\AiRequestController;
use App\Http\Controllers\AiSettingsController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SpecificationController;
use App\Http\Controllers\SpecificationVersionController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TeamMemberController;
use App\Http\Controllers\UserStoryController;
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

    Route::patch('/profile/ai-settings', [AiSettingsController::class, 'update'])->name('ai-settings.update');
    Route::delete('/profile/ai-settings', [AiSettingsController::class, 'destroy'])->name('ai-settings.destroy');

    Route::resource('teams', TeamController::class);

    Route::post('teams/{team}/members', [TeamMemberController::class, 'store'])->name('teams.members.store');
    Route::patch('teams/{team}/members/{member}', [TeamMemberController::class, 'update'])->name('teams.members.update');
    Route::delete('teams/{team}/members/{member}', [TeamMemberController::class, 'destroy'])->name('teams.members.destroy');

    Route::resource('teams.projects', ProjectController::class)->shallow();
    Route::resource('projects.specifications', SpecificationController::class)->shallow()->except(['index']);
    Route::resource('projects.user-stories', UserStoryController::class)
        ->parameters(['user-stories' => 'userStory'])
        ->shallow()->except(['index']);
    Route::resource('user-stories.acceptance-criteria', AcceptanceCriterionController::class)
        ->parameters(['user-stories' => 'userStory', 'acceptance-criteria' => 'acceptanceCriterion'])
        ->shallow()->except(['index']);

    Route::get('specifications/{specification}/versions', [SpecificationVersionController::class, 'index'])->name('specifications.versions.index');
    Route::get('specifications/{specification}/versions/compare', [SpecificationVersionController::class, 'compare'])->name('specifications.versions.compare');
    Route::get('specifications/{specification}/versions/{version}', [SpecificationVersionController::class, 'show'])->name('specifications.versions.show');
    Route::post('specifications/{specification}/versions/{version}/restore', [SpecificationVersionController::class, 'restore'])->name('specifications.versions.restore');

    Route::get('user-stories/{userStory}/versions', [SpecificationVersionController::class, 'indexForUserStory'])->name('user-stories.versions.index');
    Route::get('user-stories/{userStory}/versions/compare', [SpecificationVersionController::class, 'compareForUserStory'])->name('user-stories.versions.compare');
    Route::get('user-stories/{userStory}/versions/{version}', [SpecificationVersionController::class, 'showForUserStory'])->name('user-stories.versions.show');
    Route::post('user-stories/{userStory}/versions/{version}/restore', [SpecificationVersionController::class, 'restoreForUserStory'])->name('user-stories.versions.restore');

    Route::post('specifications/{specification}/comments', [CommentController::class, 'store'])->name('comments.store');
    Route::post('user-stories/{userStory}/comments', [CommentController::class, 'storeForUserStory'])->name('user-stories.comments.store');
    Route::post('acceptance-criteria/{acceptanceCriterion}/comments', [CommentController::class, 'storeForAcceptanceCriterion'])->name('acceptance-criteria.comments.store');
    Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

    Route::post('specifications/{specification}/ai/improve-text', [AiRequestController::class, 'improveText'])->name('ai.improve-text');
    Route::post('specifications/{specification}/ai/generate-next-steps', [AiRequestController::class, 'generateNextStepsForSpecification'])->name('ai.generate-next-steps');
    Route::post('specifications/{specification}/ai/generate-user-stories', [AiRequestController::class, 'generateUserStories'])->name('ai.generate-user-stories');
    Route::post('user-stories/{userStory}/ai/improve-text', [AiRequestController::class, 'improveTextForUserStory'])->name('user-stories.ai.improve-text');
    Route::post('user-stories/{userStory}/ai/generate-next-steps', [AiRequestController::class, 'generateNextStepsForUserStory'])->name('user-stories.ai.generate-next-steps');
    Route::post('user-stories/{userStory}/ai/generate-acceptance-criteria', [AiRequestController::class, 'generateAcceptanceCriteria'])->name('user-stories.ai.generate-acceptance-criteria');
    Route::post('acceptance-criteria/{acceptanceCriterion}/ai/improve-text', [AiRequestController::class, 'improveTextForAcceptanceCriterion'])->name('acceptance-criteria.ai.improve-text');
    Route::post('projects/{project}/ai/import-pdf', [AiImportController::class, 'store'])->name('ai.import-pdf');
    Route::get('ai-requests/{aiRequest}/status', [AiRequestController::class, 'status'])->name('ai-requests.status');
    Route::post('ai-requests/{aiRequest}/apply', [AiRequestController::class, 'apply'])->name('ai-requests.apply');
});

require __DIR__.'/auth.php';
