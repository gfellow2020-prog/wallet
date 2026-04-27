<?php

namespace App\Console\Commands;

use Database\Seeders\CategoriesSeeder;
use Illuminate\Console\Command;

class SyncCategoriesFromProducts extends Command
{
    protected $signature = 'categories:sync-from-products';

    protected $description = 'Upsert categories from products.category and normalize existing products';

    public function handle(): int
    {
        $this->callSilent('db:seed', ['--class' => CategoriesSeeder::class, '--force' => true]);
        $this->info('Categories synced from products.');
        return 0;
    }
}

