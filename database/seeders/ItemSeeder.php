<?php

namespace Database\Seeders;

use App\Models\Item;
use Illuminate\Database\Seeder;

class ItemSeeder extends Seeder
{
    /**
     * The authoritative, exact menu -- in display order.
     * Re-running this is always safe: matches by name, updates in place.
     */
    public function run(): void
    {
        $items = [
            ['name' => 'Minute Burger',                       'price' => 43,  'divisor' => 2],
            ['name' => 'Double Minute Burger',                'price' => 66,  'divisor' => 2],
            ['name' => 'Chicken Time Burger',                 'price' => 52,  'divisor' => 2],
            ['name' => 'Double Chicken Time Burger',          'price' => 72,  'divisor' => 2],
            ['name' => 'Cheesy Burger',                       'price' => 54,  'divisor' => 2],
            ['name' => 'Double Cheesy Burger',                'price' => 82,  'divisor' => 2],
            ['name' => 'Cheesydog',                           'price' => 51,  'divisor' => 2],
            ['name' => 'French Onion Franks',                 'price' => 97,  'divisor' => 2],
            ['name' => 'Hungarian Sausage',                   'price' => 114, 'divisor' => 2],
            ['name' => 'Steak Burger',                        'price' => 143, 'divisor' => 2],
            ['name' => 'Black Pepper Burger',                 'price' => 92,  'divisor' => 2],
            ['name' => 'Bacon Cheese Burger',                 'price' => 99,  'divisor' => 2],
            ['name' => 'Shawarma Cheese Burger',              'price' => 94,  'divisor' => 2],
            ['name' => 'Bacon Pizza Burger',                  'price' => 105, 'divisor' => 2],
            ['name' => '50-50 Veggie Chicken Burger',         'price' => 89,  'divisor' => 2],
            ['name' => 'Crispy Roasted Sesame Burger',        'price' => 97,  'divisor' => 2],
            ['name' => 'Crispy Chicken Chimichuri Burger',    'price' => 102, 'divisor' => 2],
            ['name' => 'Chicken Sisig Burger',                'price' => 98,  'divisor' => 2],
            ['name' => 'Lemon Black Tea',                     'price' => 26,  'divisor' => 1],
            ['name' => 'Frutwist',                            'price' => 26,  'divisor' => 1],
            ['name' => 'Coleslaw',                            'price' => 12,  'divisor' => 2],
            ['name' => 'Cheese',                              'price' => 15,  'divisor' => 2],
            ['name' => 'Ice Choco',                           'price' => 27,  'divisor' => 1],
            ['name' => 'Hot Choco',                           'price' => 20,  'divisor' => 1],
            ['name' => 'Egg',                                 'price' => 15,  'divisor' => 1],
            ['name' => 'Clover Chips',                        'price' => 15,  'divisor' => 1],
            ['name' => 'Mineral',                             'price' => 16,  'divisor' => 1],
        ];

        foreach ($items as $index => $data) {
            Item::updateOrCreate(
                ['name' => $data['name']],
                [
                    'unit' => 'pcs',
                    'price' => $data['price'],
                    'divisor' => $data['divisor'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }

        // Anything active but NOT in this authoritative list gets hidden --
        // guarantees a clean menu even if stray items exist beyond this list.
        $validNames = collect($items)->pluck('name');

        Item::where('is_active', true)
            ->whereNotIn('name', $validNames)
            ->update(['is_active' => false]);
    }
}