<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@admin.com',
            'password' => Hash::make('12341234'),
            'role' => 'admin',
            'country' => 'España',
            'city' => 'Madrid',
        ]);

        User::factory(3)->create();

        $categories = ['Electrónica', 'Ropa', 'Hogar', 'Deportes'];
        foreach ($categories as $catName) {
            $category = Category::create([
                'name' => $catName,
                'slug' => str($catName)->slug(),
            ]);

            // Crer 5 productos para categoría
            for ($i = 1; $i <= 5; $i++) {
                Product::create([
                    'category_id' => $category->id,
                    'name' => "Producto $catName $i",
                    'details' => "Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book.",
                    'description' => "Contrary to popular belief, Lorem Ipsum is not simply random text. It has roots in a piece of classical Latin literature from 45 BC, making it over 2000 years old.",
                    'price' => rand(10, 500),
                    'stock' => rand(1, 50),
                    'image' => null,
                    'is_active' => true,
                ]);
            }
        }

        // Crear un pedido
        $user = User::where('email', 'admin@admin.com')->first();
        $product = Product::first();
        $order = \App\Models\Order::create([
            'user_id' => $user->id,
            'shipping_street' => 'Calle Mayor 15, 2B',
            'shipping_city' => 'Madrid',
            'shipping_province' => 'Madrid',
            'shipping_postal_code' => '28001',
            'shipping_country' => 'España',
            'total' => $product->price,
            'status' => 'pending',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => $product->price,
        ]);
    }
}
