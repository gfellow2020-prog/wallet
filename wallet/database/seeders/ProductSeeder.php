<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $seller = User::first();
        if (! $seller) {
            $seller = User::factory()->create(['name' => 'Demo Seller', 'email' => 'seller@extracash.zm']);
        }

        $locations = [
            ['lat' => -15.4166, 'lng' => 28.2833, 'label' => 'Lusaka CBD'],
            ['lat' => -15.3875, 'lng' => 28.3228, 'label' => 'Woodlands, Lusaka'],
            ['lat' => -15.4275, 'lng' => 28.3172, 'label' => 'Kabulonga, Lusaka'],
            ['lat' => -15.4050, 'lng' => 28.2680, 'label' => 'Matero, Lusaka'],
            ['lat' => -15.4400, 'lng' => 28.3500, 'label' => 'Chelston, Lusaka'],
            ['lat' => -15.3700, 'lng' => 28.3100, 'label' => 'Rhodes Park, Lusaka'],
            ['lat' => -15.4550, 'lng' => 28.2950, 'label' => 'Chilenje, Lusaka'],
            ['lat' => -15.4200, 'lng' => 28.2450, 'label' => 'Bauleni, Lusaka'],
        ];

        // Each entry has a Unsplash/Picsum photo ID for a relevant image
        $products = [
            ['title' => 'Samsung Galaxy A54 – 128GB',         'category' => 'Electronics',  'price' => 3500.00, 'condition' => 'used',        'description' => 'Lightly used, great battery life. Original charger included.',        'photo_id' => '393'],
            ['title' => 'iPhone 13 – 256GB Space Grey',       'category' => 'Electronics',  'price' => 7200.00, 'condition' => 'used',        'description' => 'Excellent condition, minor scratch on back. iCloud removed.',          'photo_id' => '1092644'],
            ['title' => 'HP Laptop 15" – Intel i5 8GB RAM',   'category' => 'Electronics',  'price' => 5800.00, 'condition' => 'refurbished', 'description' => 'Refurbished with new battery. Windows 11 installed.',                   'photo_id' => '1496181'],
            ['title' => 'Wireless Earbuds – Anker Soundcore', 'category' => 'Electronics',  'price' => 420.00,  'condition' => 'new',         'description' => 'Brand new sealed. 40hr battery life.',                                  'photo_id' => '3394650'],
            ['title' => 'Solar Panel 100W – Monocrystalline', 'category' => 'Electronics',  'price' => 1200.00, 'condition' => 'new',         'description' => 'Perfect for load-shedding. With charge controller.',                    'photo_id' => '9875441'],
            ['title' => 'Electric Kettle 1.8L',               'category' => 'Appliances',   'price' => 180.00,  'condition' => 'new',         'description' => '1800W rapid boil, auto shut-off. Brand new.',                          'photo_id' => '1714208'],
            ['title' => "Men's Chitenge Shirt – XL",          'category' => 'Clothing',     'price' => 95.00,   'condition' => 'new',         'description' => 'Handmade Zambian fabric, XL size.',                                     'photo_id' => '1152077'],
            ['title' => "Women's Dress – Ankara Print M",     'category' => 'Clothing',     'price' => 130.00,  'condition' => 'new',         'description' => 'Beautiful Ankara print, size M. Ready to wear.',                        'photo_id' => '1536451961'],
            ['title' => 'Nike Air Max 270 – Size 42',         'category' => 'Clothing',     'price' => 650.00,  'condition' => 'used',        'description' => 'Worn twice, clean. Size 42 EU.',                                        'photo_id' => '1542291026'],
            ['title' => 'School Bag – Samsonite Backpack',    'category' => 'Clothing',     'price' => 260.00,  'condition' => 'used',        'description' => 'Great condition school bag.',                                           'photo_id' => '1553062137'],
            ['title' => 'Fresh Organic Tomatoes – 5kg',       'category' => 'Food',         'price' => 60.00,   'condition' => 'new',         'description' => 'Farm-fresh from Chisamba. Delivered to your area.',                     'photo_id' => '1592924599'],
            ['title' => 'Kapenta – Dried 1kg',                'category' => 'Food',         'price' => 85.00,   'condition' => 'new',         'description' => 'Sun-dried Lake Kariba kapenta. Quality guaranteed.',                    'photo_id' => '1504674900'],
            ['title' => 'Honey – Pure Raw 500ml',             'category' => 'Food',         'price' => 120.00,  'condition' => 'new',         'description' => 'Wild-harvested from Western Province. No additives.',                   'photo_id' => '1471943038'],
            ['title' => 'Maize Meal – Breakfast 25kg',        'category' => 'Food',         'price' => 210.00,  'condition' => 'new',         'description' => 'Premium breakfast meal, straight from the mill.',                       'photo_id' => '1574323347'],
            ['title' => 'Wooden Coffee Table – Handmade',     'category' => 'Furniture',    'price' => 850.00,  'condition' => 'new',         'description' => 'Locally crafted hardwood. 120cm × 60cm.',                               'photo_id' => '1555041469'],
            ['title' => 'Wall Mirror 80cm × 60cm',            'category' => 'Home',         'price' => 340.00,  'condition' => 'new',         'description' => 'Bevelled edges, ready to hang.',                                        'photo_id' => '1618221195'],
            ['title' => '3-Seater Sofa – Grey Fabric',        'category' => 'Furniture',    'price' => 2800.00, 'condition' => 'used',        'description' => 'Good condition, minimal wear. Self-collection only.',                   'photo_id' => '1555041469'],
            ['title' => 'Bicycle – Mountain MTB 26"',         'category' => 'Sports',       'price' => 780.00,  'condition' => 'used',        'description' => 'Good working order, new tyres fitted.',                                 'photo_id' => '1485965120756'],
            ['title' => 'School Textbooks – Grade 12 Bundle', 'category' => 'Books',        'price' => 150.00,  'condition' => 'used',        'description' => '8 core subjects. Good condition.',                                      'photo_id' => '1497633762'],
            ['title' => 'Cooking Gas – 15kg Cylinder',        'category' => 'Home',         'price' => 390.00,  'condition' => 'new',         'description' => 'Full 15kg cylinder. ZESCO/Puma approved.',                              'photo_id' => '1558618047'],
            ['title' => 'Generator 2.5KVA – Lifan',           'category' => 'Electronics',  'price' => 3200.00, 'condition' => 'used',        'description' => 'Used for 6 months. Starts first pull.',                                 'photo_id' => '1565514020'],
            ['title' => 'Gas Stove 4-Burner – Mika',         'category' => 'Appliances',   'price' => 1200.00, 'condition' => 'new',         'description' => '4-burner gas stove, brand new in box.',                                 'photo_id' => '1556909114'],
            ['title' => 'Football – Adidas Size 5',           'category' => 'Sports',       'price' => 180.00,  'condition' => 'new',         'description' => 'Official size 5 football, brand new.',                                  'photo_id' => '1579952168'],
            ['title' => 'Curtains Set – 5 Windows',           'category' => 'Home',         'price' => 320.00,  'condition' => 'new',         'description' => 'Blackout curtains set for 5 windows.',                                  'photo_id' => '1558618047'],
            ['title' => 'Standing Fan – 3-Speed',             'category' => 'Appliances',   'price' => 280.00,  'condition' => 'new',         'description' => '3-speed standing fan, 16 inch blade.',                                  'photo_id' => '1558618047'],
            ['title' => 'Xbox Controller',                    'category' => 'Electronics',  'price' => 450.00,  'condition' => 'used',        'description' => 'Xbox wireless controller, barely used.',                                'photo_id' => '1486572788'],
        ];

        $cashbackRate = 0.02;
        Storage::disk('public')->makeDirectory('products');

        foreach ($products as $i => $p) {
            $loc = $locations[$i % count($locations)];
            $latJitter = (rand(-50, 50) / 10000);
            $lngJitter = (rand(-50, 50) / 10000);

            // Download & compress image from Unsplash (800×600, JPEG q72)
            $imagePath = $this->downloadAndCompress($p['photo_id']);

            Product::updateOrCreate(
                ['title' => $p['title'], 'user_id' => $seller->id],
                [
                    'user_id' => $seller->id,
                    'description' => $p['description'],
                    'category' => $p['category'],
                    'price' => $p['price'],
                    'cashback_rate' => $cashbackRate,
                    'cashback_amount' => round($p['price'] * $cashbackRate, 2),
                    'condition' => $p['condition'],
                    'stock' => rand(1, 5),
                    'is_active' => true,
                    'latitude' => $loc['lat'] + $latJitter,
                    'longitude' => $loc['lng'] + $lngJitter,
                    'location_label' => $loc['label'],
                    'image_url' => $imagePath,
                ]
            );

            $this->command->info("  ✔ {$p['title']}");
        }
    }

    /**
     * Download from Unsplash, resize to 800px wide, compress to JPEG q72.
     * Returns the storage-relative path.
     */
    private function downloadAndCompress(string $photoId): string
    {
        $url = "https://images.unsplash.com/photo-{$photoId}?w=800&q=72&fm=jpg&fit=crop";
        $filename = 'products/'.Str::uuid().'.jpg';

        try {
            $ctx = stream_context_create(['http' => ['timeout' => 15]]);
            $data = @file_get_contents($url, false, $ctx);

            if ($data === false || strlen($data) < 1000) {
                // Fallback: picsum placeholder
                $data = @file_get_contents('https://picsum.photos/800/600', false, $ctx);
            }

            if ($data !== false) {
                $src = @imagecreatefromstring($data);
                if ($src) {
                    [$w, $h] = [imagesx($src), imagesy($src)];
                    $maxW = 800;
                    if ($w > $maxW) {
                        $newH = (int) round($h * $maxW / $w);
                        $dst = imagecreatetruecolor($maxW, $newH);
                        imagecopyresampled($dst, $src, 0, 0, 0, 0, $maxW, $newH, $w, $h);
                        imagedestroy($src);
                        $src = $dst;
                    }
                    ob_start();
                    imagejpeg($src, null, 72);
                    $jpeg = ob_get_clean();
                    imagedestroy($src);
                    Storage::disk('public')->put($filename, $jpeg);

                    return $filename;
                }
            }
        } catch (\Throwable $e) {
            // Silent — will leave image_url null
        }

        return $filename; // will be empty but won't crash
    }
}
