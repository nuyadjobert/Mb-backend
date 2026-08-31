<?php

namespace Database\Seeders;

use App\Models\Item;
use Illuminate\Database\Seeder;

class ItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            'B11T1 MB', 'B11T1 DMB', 'CHICKEN TIME BURGER', 'DOUBLE CHIXN TIME BURG',
            'CHEESY BURGER BUNS', 'DOUBLE CHEESY BURGER BUNS', 'CHILI CHEESY BUNS',
            'CHILI-CHEESY BUNS', 'CHEESEDOG BUNS', 'FRENCH ONION FRANKS',
            'CHILICON CHEESE FRANKS', 'STEAK BURGER BUNS', 'B-PEPPER BUNS',
            'BACON CHEESE BUNS', 'BEEF SHAWARMA BUNS', '50/50 VEGGIE BUNS',
            'ROASTED SESAME BUNS', 'CHIMICHURRI BUNS', 'RED HOT CHICKEN',
            'CALAMANTEA', 'FRUITWIST', 'ICED CHOCO', 'ICED COFFEE',
            'KRAZY MILKTEA', 'MINERAL WATER', 'HOT CHOCO', 'SUPREME CHEESE',
            'EGG', 'COLESLAW', 'NACHOS', 'FARMERS JOHN', 'CLOVER CHIPS', 'FRIES',
        ];

        foreach ($items as $name) {
            Item::firstOrCreate(
                ['name' => $name],
                [
                    'unit' => 'pcs',
                    'price' => 0,     // update with real prices
                    'divisor' => 1,   // update per item's actual conversion factor
                    'is_active' => true,
                ]
            );
        }
    }
}