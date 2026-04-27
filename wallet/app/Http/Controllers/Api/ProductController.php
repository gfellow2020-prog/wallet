<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class ProductController extends Controller
{
    /**
     * GET /api/products/nearby
     */
    public function nearby(Request $request)
    {
        $lat = (float) $request->input('lat', -15.4166);
        $lng = (float) $request->input('lng', 28.2833);
        $radius = (float) $request->input('radius', 25);

        $query = Product::active()
            ->with('seller:id,name')
            ->where('user_id', '!=', $request->user()->id)
            ->nearby($lat, $lng, $radius);

        if ($request->filled('category')) {
            $raw = trim((string) $request->category);
            $cat = Category::query()->where('slug', $raw)->first();
            $query->where('category', $cat?->name ?? $raw);
        }

        $products = $query->paginate((int) $request->input('per_page', 20));

        // Append full URL to image_path
        $products->getCollection()->transform(fn ($p) => $this->appendImageUrl($p));

        return response()->json($products);
    }

    /**
     * GET /api/products/mine
     */
    public function mine(Request $request)
    {
        $products = Product::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        $products->getCollection()->transform(fn ($p) => $this->appendImageUrl($p));

        return response()->json($products);
    }

    /**
     * GET /api/products/{product}
     */
    public function show(Product $product)
    {
        $product->increment('clicks');
        $product->load('seller:id,name');

        $product->append('qr_payload');

        return response()->json($this->appendImageUrl($product));
    }

    /**
     * POST /api/products  (multipart/form-data)
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'category' => 'nullable|string|max:100',
            'price' => 'required|numeric|min:0.01',
            'condition' => 'nullable|in:new,used,refurbished',
            'stock' => 'nullable|integer|min:1',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'location_label' => 'nullable|string|max:255',
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:10240',
        ]);

        $imagePath = $this->storeCompressed($request->file('image'));

        $cashbackRate = 0.02;

        $product = Product::create([
            'user_id' => $request->user()->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? null,
            'price' => $data['price'],
            'condition' => $data['condition'] ?? 'new',
            'stock' => $data['stock'] ?? 1,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'location_label' => $data['location_label'] ?? null,
            'image_url' => $imagePath,
            'cashback_rate' => $cashbackRate,
            'cashback_amount' => round($data['price'] * $cashbackRate, 2),
        ]);

        $product->append('qr_payload');

        return response()->json($this->appendImageUrl($product), 201);
    }

    /**
     * PATCH /api/products/{product}
     */
    public function update(Request $request, Product $product)
    {
        abort_if($product->user_id !== $request->user()->id, 403, 'Not your listing');

        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:2000',
            'category' => 'nullable|string|max:100',
            'price' => 'sometimes|numeric|min:0.01',
            'condition' => 'nullable|in:new,used,refurbished',
            'stock' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'location_label' => 'nullable|string|max:255',
            'image' => 'sometimes|image|mimes:jpeg,png,jpg,webp|max:10240',
        ]);

        if ($request->hasFile('image')) {
            // Delete old image
            if ($product->image_url) {
                Storage::disk('public')->delete($product->image_url);
            }
            $data['image_url'] = $this->storeCompressed($request->file('image'));
        }
        unset($data['image']);

        if (isset($data['price'])) {
            $rate = $product->cashback_rate ?: 0.02;
            $data['cashback_amount'] = round($data['price'] * $rate, 2);
        }

        $product->update($data);

        $product->append('qr_payload');

        return response()->json($this->appendImageUrl($product));
    }

    /**
     * DELETE /api/products/{product}
     */
    public function destroy(Request $request, Product $product)
    {
        abort_if($product->user_id !== $request->user()->id, 403, 'Not your listing');
        if ($product->image_url) {
            Storage::disk('public')->delete($product->image_url);
        }
        $product->delete();

        return response()->json(['message' => 'Listing deleted.']);
    }

    /* ── Helpers ──────────────────────────────────────────────── */

    /**
     * Compress & store an uploaded image. Returns the storage path.
     * Target: JPEG, max 800px wide, quality 72 → typically 40–80 KB.
     */
    private function storeCompressed(UploadedFile $file): string
    {
        $filename = 'products/'.Str::uuid().'.jpg';

        $image = File::get($file->getRealPath());

        // Use GD to resize & compress (no extra package needed)
        $src = imagecreatefromstring($image);
        [$w, $h] = [imagesx($src), imagesy($src)];

        $maxW = 800;
        if ($w > $maxW) {
            $newH = (int) round($h * $maxW / $w);
            $dst = imagecreatetruecolor($maxW, $newH);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $maxW, $newH, $w, $h);
            imagedestroy($src);
            $src = $dst;
        }

        // Capture to buffer
        ob_start();
        imagejpeg($src, null, 72); // quality 72
        $jpeg = ob_get_clean();
        imagedestroy($src);

        Storage::disk('public')->put($filename, $jpeg);

        return $filename;
    }

    /**
     * Add full image URL to a product model.
     */
    private function appendImageUrl(Product $product): Product
    {
        if ($product->image_url && ! str_starts_with($product->image_url, 'http')) {
            $product->image_url = url('/storage/'.ltrim($product->image_url, '/'));
        }

        return $product;
    }
}
