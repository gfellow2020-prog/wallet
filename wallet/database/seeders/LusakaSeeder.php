<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class LusakaSeeder extends Seeder
{
    public function run(): void
    {
        // ── 20 Lusaka users ───────────────────────────────────────────
        $userData = [
            ['name' => 'Chanda Mutale',    'email' => 'chanda@zm.test',    'phone' => '+260971100001'],
            ['name' => 'Mwansa Bwalya',    'email' => 'mwansa@zm.test',    'phone' => '+260971100002'],
            ['name' => 'Namukolo Phiri',   'email' => 'namukolo@zm.test',  'phone' => '+260971100003'],
            ['name' => 'Mutinta Mwale',    'email' => 'mutinta@zm.test',   'phone' => '+260971100004'],
            ['name' => 'Kabwe Musonda',    'email' => 'kabwe@zm.test',     'phone' => '+260971100005'],
            ['name' => 'Esther Lungu',     'email' => 'esther@zm.test',    'phone' => '+260971100006'],
            ['name' => 'Bwalya Chilufya',  'email' => 'bwalya@zm.test',    'phone' => '+260971100007'],
            ['name' => 'Nkandu Siame',     'email' => 'nkandu@zm.test',    'phone' => '+260971100008'],
            ['name' => 'Mapalo Tembo',     'email' => 'mapalo@zm.test',    'phone' => '+260971100009'],
            ['name' => 'Lweendo Banda',    'email' => 'lweendo@zm.test',   'phone' => '+260971100010'],
            ['name' => 'Inutu Mwamba',     'email' => 'inutu@zm.test',     'phone' => '+260971100011'],
            ['name' => 'Kondwani Phiri',   'email' => 'kondwani@zm.test',  'phone' => '+260971100012'],
            ['name' => 'Chileshe Kapata',  'email' => 'chileshe@zm.test',  'phone' => '+260971100013'],
            ['name' => 'Musonda Chalwe',   'email' => 'musonda@zm.test',   'phone' => '+260971100014'],
            ['name' => 'Nambeye Mulenga',  'email' => 'nambeye@zm.test',   'phone' => '+260971100015'],
            ['name' => 'Zikomo Tembo',     'email' => 'zikomo@zm.test',    'phone' => '+260971100016'],
            ['name' => 'Kachinga Mutale',  'email' => 'kachinga@zm.test',  'phone' => '+260971100017'],
            ['name' => 'Wezi Nkonde',      'email' => 'wezi@zm.test',      'phone' => '+260971100018'],
            ['name' => 'Bupe Chitalu',     'email' => 'bupe@zm.test',      'phone' => '+260971100019'],
            ['name' => 'Nchimunya Phiri',  'email' => 'nchimunya@zm.test', 'phone' => '+260971100020'],
        ];

        // Lusaka neighbourhoods with real coordinates
        $locations = [
            ['lat' => -15.4166, 'lng' => 28.2833, 'label' => 'Lusaka CBD'],
            ['lat' => -15.3875, 'lng' => 28.3228, 'label' => 'Woodlands'],
            ['lat' => -15.4275, 'lng' => 28.3172, 'label' => 'Kabulonga'],
            ['lat' => -15.4050, 'lng' => 28.2680, 'label' => 'Matero'],
            ['lat' => -15.4400, 'lng' => 28.3500, 'label' => 'Chelston'],
            ['lat' => -15.3700, 'lng' => 28.3100, 'label' => 'Rhodes Park'],
            ['lat' => -15.4550, 'lng' => 28.2950, 'label' => 'Chilenje'],
            ['lat' => -15.4200, 'lng' => 28.2450, 'label' => 'Bauleni'],
            ['lat' => -15.4000, 'lng' => 28.3400, 'label' => 'Olympia Park'],
            ['lat' => -15.4600, 'lng' => 28.3200, 'label' => 'Ibex Hill'],
            ['lat' => -15.3600, 'lng' => 28.2900, 'label' => 'Northmead'],
            ['lat' => -15.4300, 'lng' => 28.2600, 'label' => 'Kalingalinga'],
            ['lat' => -15.4100, 'lng' => 28.2300, 'label' => 'Kabwata'],
            ['lat' => -15.3800, 'lng' => 28.2700, 'label' => 'Emmasdale'],
            ['lat' => -15.4750, 'lng' => 28.3100, 'label' => 'Lilayi'],
            ['lat' => -15.3500, 'lng' => 28.3300, 'label' => 'Meanwood'],
            ['lat' => -15.4450, 'lng' => 28.2750, 'label' => 'Garden'],
            ['lat' => -15.4250, 'lng' => 28.3600, 'label' => 'Leopards Hill'],
            ['lat' => -15.3950, 'lng' => 28.2100, 'label' => 'Chawama'],
            ['lat' => -15.4850, 'lng' => 28.2800, 'label' => 'Kafue Road'],
        ];

        // Products pool – 40 items across various categories
        $productPool = [
            // Electronics
            ['title' => 'Tecno Spark 20 – 128GB',            'cat' => 'Electronics',  'price' => 2800, 'cond' => 'new',        'desc' => 'Brand new sealed in box. Dual SIM, 5000mAh battery.'],
            ['title' => 'Samsung Galaxy A14 – 64GB',          'cat' => 'Electronics',  'price' => 2100, 'cond' => 'used',       'desc' => 'Good condition. Minor screen scratch, works perfectly.'],
            ['title' => 'itel Vision 3 Plus',                 'cat' => 'Electronics',  'price' => 950,  'cond' => 'new',        'desc' => 'New sealed. Great entry-level phone with big screen.'],
            ['title' => 'Xiaomi Redmi Note 12',               'cat' => 'Electronics',  'price' => 3600, 'cond' => 'used',       'desc' => 'Excellent condition, 6 months old. With original box.'],
            ['title' => 'Laptop Acer Aspire 3 – i3 8GB',     'cat' => 'Electronics',  'price' => 4500, 'cond' => 'refurbished', 'desc' => 'Fully serviced, new SSD. Windows 11 activated.'],
            ['title' => 'Bluetooth Speaker JBL Go 3',         'cat' => 'Electronics',  'price' => 380,  'cond' => 'new',        'desc' => 'Waterproof, 5hr playtime. Brand new.'],
            ['title' => 'USB-C Power Bank 20000mAh',          'cat' => 'Electronics',  'price' => 290,  'cond' => 'new',        'desc' => 'Fast charge 22.5W. Charges 3 devices simultaneously.'],
            ['title' => 'Smart TV 32" – Skyview Android',     'cat' => 'Electronics',  'price' => 2900, 'cond' => 'new',        'desc' => 'Android 11, built-in WiFi, Netflix/YouTube ready.'],
            ['title' => 'DSTV Decoder + Dish Full Kit',       'cat' => 'Electronics',  'price' => 850,  'cond' => 'used',       'desc' => 'Full setup including dish, cable and remote. Works great.'],
            ['title' => 'HP Ink Tank 415 Printer',            'cat' => 'Electronics',  'price' => 1800, 'cond' => 'new',        'desc' => 'Wi-Fi enabled, ink-tank system. Full ink bottles included.'],

            // Clothing & Shoes
            ['title' => 'Chitenge Wrap Dress – Ladies L',     'cat' => 'Clothing',     'price' => 110,  'cond' => 'new',        'desc' => 'Locally made Zambian print. Size L. Ready to ship.'],
            ['title' => 'Men\'s Formal Suit – Navy Blue 42',  'cat' => 'Clothing',     'price' => 480,  'cond' => 'used',       'desc' => 'Worn twice. 3-piece set (jacket, trousers, waistcoat).'],
            ['title' => 'Adidas Slides – Size 43',            'cat' => 'Clothing',     'price' => 150,  'cond' => 'new',        'desc' => 'Original Adidas Adilette. Size 43. Brand new.'],
            ['title' => 'Baby Clothes Bundle 0–6 Months',     'cat' => 'Clothing',     'price' => 180,  'cond' => 'new',        'desc' => '10 pieces: onesies, rompers, socks. Unisex colours.'],
            ['title' => 'School Bag – Samsonite Backpack',    'cat' => 'Clothing',     'price' => 260,  'cond' => 'used',       'desc' => 'Good condition, all zips working. Laptop compartment.'],

            // Food & Groceries
            ['title' => 'Fresh Cow Milk – 5 Litres Daily',   'cat' => 'Food',         'price' => 90,   'cond' => 'new',        'desc' => 'Farm fresh delivered daily in Lusaka. Contact for schedule.'],
            ['title' => 'Sweet Potatoes – 10kg Bag',          'cat' => 'Food',         'price' => 70,   'cond' => 'new',        'desc' => 'Harvested from Chongwe. Delivered to your home.'],
            ['title' => 'Groundnuts – Roasted 2kg',           'cat' => 'Food',         'price' => 55,   'cond' => 'new',        'desc' => 'Freshly roasted, no additives. Mwinilunga variety.'],
            ['title' => 'Soy Beans – 25kg Bag',               'cat' => 'Food',         'price' => 380,  'cond' => 'new',        'desc' => 'Grade A from Mkushi farm. Good for animal feed or cooking.'],
            ['title' => 'Caterpillars (Ifinkubala) – 500g',   'cat' => 'Food',         'price' => 95,   'cond' => 'new',        'desc' => 'Dried, ready to fry. Northern Province harvest.'],

            // Home & Furniture
            ['title' => 'Queen Bed Frame – Wooden',           'cat' => 'Furniture',    'price' => 1800, 'cond' => 'used',       'desc' => 'Solid hardwood, dismantled for easy transport. Good condition.'],
            ['title' => 'Study Desk with Chair',               'cat' => 'Furniture',    'price' => 650,  'cond' => 'used',       'desc' => 'Perfect for students. Spacious desk + padded chair.'],
            ['title' => 'Curtains Set – 5 Windows',           'cat' => 'Home',         'price' => 320,  'cond' => 'new',        'desc' => 'Blackout curtains. Brown/cream colour. 5 pairs.'],
            ['title' => 'Gas Stove 4-Burner – Mika',          'cat' => 'Appliances',   'price' => 1200, 'cond' => 'new',        'desc' => 'Brand new, auto-ignition. Silver finish.'],
            ['title' => 'Water Filter – 10-Stage Reverse Osmosis', 'cat' => 'Home',    'price' => 1500, 'cond' => 'new',        'desc' => 'Under-sink installation. Pure water, removes 99% contaminants.'],

            // Sports & Recreation
            ['title' => 'Football – Adidas Size 5',           'cat' => 'Sports',       'price' => 85,   'cond' => 'new',        'desc' => 'Match quality, FIFA inspected. Sealed.'],
            ['title' => 'Gym Dumbbells Set – 20kg Pair',      'cat' => 'Sports',       'price' => 550,  'cond' => 'used',       'desc' => 'Cast iron, hex shape. Selling as pair. Slight surface rust.'],
            ['title' => 'Fishing Rod & Reel Combo',           'cat' => 'Sports',       'price' => 220,  'cond' => 'new',        'desc' => 'Telescopic rod 2.1m + spinning reel. Ready to fish.'],
            ['title' => 'Volleyball Net & Ball Set',           'cat' => 'Sports',       'price' => 190,  'cond' => 'new',        'desc' => 'Official dimensions, steel cable top. Great for schools.'],

            // Books & Education
            ['title' => 'UNZA Past Papers 2018–2023',         'cat' => 'Books',        'price' => 60,   'cond' => 'used',       'desc' => 'All faculties. Scanned and printed. Collected from Lusaka CBD.'],
            ['title' => 'ZIMSEC/ECZ Grade 12 Revision Books', 'cat' => 'Books',        'price' => 120,  'cond' => 'used',       'desc' => '7 subjects: Maths, Physics, Chemistry, Bio, English, History, Geo.'],
            ['title' => 'Python Programming – 3 Book Bundle', 'cat' => 'Books',        'price' => 250,  'cond' => 'used',       'desc' => 'Learn Python + Django + Data Science. Good condition.'],

            // Appliances
            ['title' => 'Washing Machine – Samsung 7kg',      'cat' => 'Appliances',   'price' => 3800, 'cond' => 'used',       'desc' => 'Top loader, 7kg. Works perfectly. Selling due to upgrade.'],
            ['title' => 'Standing Fan – 3-Speed',             'cat' => 'Appliances',   'price' => 190,  'cond' => 'new',        'desc' => '16" blade, remote control, timer function. Brand new.'],
            ['title' => 'Rice Cooker 1.8L – National',        'cat' => 'Appliances',   'price' => 140,  'cond' => 'new',        'desc' => 'Keeps warm after cooking. Non-stick pot. Great for families.'],

            // Other / Misc
            ['title' => 'Chicken Coop – Prefab 50 Birds',     'cat' => 'Other',        'price' => 2200, 'cond' => 'new',        'desc' => 'Galvanised iron, ventilated. Delivered in Lusaka.'],
            ['title' => 'Garden Wheelbarrow – Heavy Duty',    'cat' => 'Other',        'price' => 380,  'cond' => 'used',       'desc' => '200kg capacity, solid tyre. Perfect working order.'],
            ['title' => 'Baby Pram – Graco Stroller',         'cat' => 'Other',        'price' => 750,  'cond' => 'used',       'desc' => 'Folds flat, reclinable, sunshade. Used for 6 months.'],
            ['title' => 'Wedding Tent – 10m × 20m',           'cat' => 'Other',        'price' => 5500, 'cond' => 'used',       'desc' => 'Marquee-style tent for hire or purchase. White with poles.'],
            ['title' => 'Sewing Machine – Brother Mechanical', 'cat' => 'Other',       'price' => 1100, 'cond' => 'used',       'desc' => 'Multi-stitch mechanical machine. Comes with accessories.'],
        ];

        $cashbackRate = 0.02;
        $productIndex = 0;

        foreach ($userData as $i => $u) {
            // Create or find user
            $user = User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'password' => Hash::make('password'),
                ]
            );

            // Ensure wallet
            Wallet::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'available_balance' => rand(500, 50000) + (rand(0, 99) / 100),
                    'currency' => 'ZMW',
                ]
            );

            // Each user gets 2 products
            for ($p = 0; $p < 2; $p++) {
                $product = $productPool[$productIndex % count($productPool)];
                $productIndex++;
                $loc = $locations[$i % count($locations)];
                $latJitter = rand(-80, 80) / 10000;
                $lngJitter = rand(-80, 80) / 10000;

                Product::updateOrCreate(
                    ['title' => $product['title'], 'user_id' => $user->id],
                    [
                        'user_id' => $user->id,
                        'description' => $product['desc'],
                        'category' => $product['cat'],
                        'price' => $product['price'],
                        'cashback_rate' => $cashbackRate,
                        'cashback_amount' => round($product['price'] * $cashbackRate, 2),
                        'condition' => $product['cond'],
                        'stock' => rand(1, 8),
                        'is_active' => true,
                        'latitude' => $loc['lat'] + $latJitter,
                        'longitude' => $loc['lng'] + $lngJitter,
                        'location_label' => $loc['label'].', Lusaka',
                    ]
                );
            }
        }

        $this->command->info('✅  Seeded 20 Lusaka users with wallets and 40 products.');
    }
}
