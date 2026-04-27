<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategoriesSeeder extends Seeder
{
    private function slugify(string $name): string
    {
        $slug = Str::slug($name, '-');
        return $slug !== '' ? $slug : 'category';
    }

    private function canonicalName(string $raw): string
    {
        $s = trim(preg_replace('/\s+/', ' ', $raw) ?? '');
        $s = mb_strtolower($s);
        // Title Case each word (keeps “&” etc. readable)
        return trim(mb_convert_case($s, MB_CASE_TITLE, 'UTF-8'));
    }

    /**
     * Seed categories from existing products.category and normalize product categories.
     */
    public function run(): void
    {
        $rows = DB::table('products')
            ->select('category')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->get();

        $slugToCanonical = [];

        foreach ($rows as $r) {
            $raw = (string) $r->category;
            $canonical = $this->canonicalName($raw);
            if ($canonical === '') {
                continue;
            }
            $slug = $this->slugify($canonical);

            // Keep first canonical label for each slug
            if (! isset($slugToCanonical[$slug])) {
                $slugToCanonical[$slug] = $canonical;
            }

            Category::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $slugToCanonical[$slug],
                    'sort_order' => 0,
                    'is_active' => true,
                ]
            );
        }

        // Normalize existing products.category values to canonical names.
        foreach ($slugToCanonical as $slug => $canonical) {
            // Update products whose slugified(category) matches this slug.
            // MySQL doesn't have Str::slug, so do it in PHP by scanning in chunks.
            DB::table('products')
                ->select('id', 'category')
                ->whereNotNull('category')
                ->where('category', '!=', '')
                ->orderBy('id')
                ->chunkById(500, function ($chunk) use ($slug, $canonical) {
                    $ids = [];
                    foreach ($chunk as $p) {
                        $cat = (string) $p->category;
                        $canon = trim(mb_convert_case(mb_strtolower(trim(preg_replace('/\s+/', ' ', $cat) ?? '')), MB_CASE_TITLE, 'UTF-8'));
                        if ($canon === '') {
                            continue;
                        }
                        if (Str::slug($canon, '-') === $slug && $cat !== $canonical) {
                            $ids[] = (int) $p->id;
                        }
                    }
                    if (! empty($ids)) {
                        DB::table('products')->whereIn('id', $ids)->update(['category' => $canonical]);
                    }
                });
        }
    }
}

