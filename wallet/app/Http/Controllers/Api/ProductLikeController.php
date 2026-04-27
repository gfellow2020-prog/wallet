<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductLike;
use Illuminate\Http\Request;

class ProductLikeController extends Controller
{
    /** POST /api/products/{product}/like  — toggles like on/off */
    public function toggle(Request $request, Product $product)
    {
        $userId = $request->user()->id;

        $existing = ProductLike::where('product_id', $product->id)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            $existing->delete();
            $liked = false;
        } else {
            ProductLike::create([
                'product_id' => $product->id,
                'user_id' => $userId,
            ]);
            $liked = true;
        }

        $count = ProductLike::where('product_id', $product->id)->count();

        return response()->json([
            'liked' => $liked,
            'like_count' => $count,
        ]);
    }

    /** GET /api/products/{product}/like  — check if current user has liked */
    public function status(Request $request, Product $product)
    {
        $liked = ProductLike::where('product_id', $product->id)
            ->where('user_id', $request->user()->id)
            ->exists();

        $count = ProductLike::where('product_id', $product->id)->count();

        return response()->json([
            'liked' => $liked,
            'like_count' => $count,
        ]);
    }
}
