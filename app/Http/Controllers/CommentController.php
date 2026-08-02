<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Models\Comment;
use App\Models\Specification;
use Illuminate\Support\Facades\Auth;

class CommentController extends Controller
{
    public function store(StoreCommentRequest $request, Specification $specification)
    {
        $specification->comments()->create([
            ...$request->validated(),
            'user_id' => Auth::id(),
        ]);

        return redirect()->route('specifications.show', $specification)->with('status', 'Comment added.');
    }

    public function destroy(Comment $comment)
    {
        $this->authorize('delete', $comment);

        $specification = $comment->specification;
        $comment->delete();

        return redirect()->route('specifications.show', $specification)->with('status', 'Comment deleted.');
    }
}
