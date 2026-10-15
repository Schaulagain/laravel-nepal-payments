<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Demo cloud products, priced in paisa.
     */
    public function run(): void
    {
        $products = [
            ['name' => 'VPS Starter', 'category' => 'vps', 'description' => '1 vCPU, 2 GB RAM, 40 GB SSD, monthly.', 'price' => 150000],
            ['name' => 'Domain Registration', 'category' => 'domain', 'description' => 'One .com domain for a year, with DNS management.', 'price' => 135000],
            ['name' => 'Business Email', 'category' => 'email', 'description' => 'Five mailboxes on your own domain, monthly.', 'price' => 80000],
        ];

        foreach ($products as $product) {
            Product::query()->updateOrCreate(['name' => $product['name']], $product);
        }
    }
}
