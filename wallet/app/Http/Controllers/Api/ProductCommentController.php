<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductComment;
use Illuminate\Http\Request;

class ProductCommentController extends Controller
{
    /** GET /api/products/{product}/comments */
    public function index(Product $product)
    {
        $comments = $product->comments()
            ->with('user:id,name')
            ->paginate(30);

        return response()->json($comments);
    }

    /** POST /api/products/{product}/comments */
    public function store(Request $request, Product $product)
    {
        $request->validate(['body' => 'required|string|max:1000']);

        $comment = ProductComment::create([
            'product_id' => $product->id,
            'user_id' => $request->user()->id,
            'body' => trim($request->body),
        ]);

        $comment->load('user:id,name');

        return response()->json($comment, 201);
    }

    /** DELETE /api/products/{product}/comments/{comment} */
    public function destroy(Request $request, Product $product, ProductComment $comment)
    {
        if ($comment->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $comment->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
