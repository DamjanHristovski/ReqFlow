<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Models\AcceptanceCriterion;
use App\Models\Comment;
use App\Models\Specification;
use App\Models\UserStory;
use Illuminate\Support\Facades\Auth;

class CommentController extends Controller
{
    public function store(StoreCommentRequest $request, Specification $specification)
    {
        return $this->createComment($request, $specification);
    }

    public function storeForUserStory(StoreCommentRequest $request, UserStory $userStory)
    {
        return $this->createComment($request, $userStory);
    }

    public function storeForAcceptanceCriterion(StoreCommentRequest $request, AcceptanceCriterion $acceptanceCriterion)
    {
        return $this->createComment($request, $acceptanceCriterion);
    }

    public function destroy(Comment $comment)
    {
        $this->authorize('delete', $comment);

        $commentable = $comment->commentable();
        $comment->delete();

        return redirect()->route($this->showRouteFor($commentable), $commentable)->with('status', 'Comment deleted.');
    }

    private function createComment(StoreCommentRequest $request, Specification|UserStory|AcceptanceCriterion $commentable)
    {
        $commentable->comments()->create([
            ...$request->validated(),
            'user_id' => Auth::id(),
        ]);

        return redirect()->route($this->showRouteFor($commentable), $commentable)->with('status', 'Comment added.');
    }

    private function showRouteFor(Specification|UserStory|AcceptanceCriterion $commentable): string
    {
        return match (true) {
            $commentable instanceof Specification => 'specifications.show',
            $commentable instanceof UserStory => 'user-stories.show',
            $commentable instanceof AcceptanceCriterion => 'acceptance-criteria.show',
        };
    }
}
