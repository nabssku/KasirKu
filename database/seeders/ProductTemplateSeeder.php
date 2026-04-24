<?php

namespace Database\Seeders;

use App\Models\ProductTemplate;
use Illuminate\Database\Seeder;

class ProductTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Cafe (FnB)',
                'category_type' => 'FnB',
                'description' => 'Template standar untuk Cafe atau Coffee Shop. Berisi kopi, non-kopi, dan camilan.',
                'data' => [
                    'categories' => [
                        [
                            'name' => 'Coffee',
                            'products' => [
                                [
                                    'name' => 'Americano',
                                    'price' => 15000,
                                    'stock' => 10,
                                    'modifier_groups' => [
                                        [
                                            'name' => 'Level Gula',
                                            'required' => true,
                                            'min_select' => 1,
                                            'max_select' => 1,
                                            'modifiers' => [
                                                ['name' => 'No Sugar', 'price' => 0],
                                                ['name' => 'Less Sugar', 'price' => 0],
                                                ['name' => 'Normal Sugar', 'price' => 0],
                                            ]
                                        ],
                                        [
                                            'name' => 'Extra',
                                            'modifiers' => [
                                                ['name' => 'Extra Shot', 'price' => 5000],
                                            ]
                                        ]
                                    ]
                                ],
                                ['name' => 'Cafe Latte', 'price' => 20000, 'stock' => 10],
                                ['name' => 'Cappuccino', 'price' => 20000, 'stock' => 10],
                            ]
                        ],
                        [
                            'name' => 'Non-Coffee',
                            'products' => [
                                ['name' => 'Matcha Latte', 'price' => 22000, 'stock' => 10],
                                ['name' => 'Chocolate', 'price' => 18000, 'stock' => 10],
                                ['name' => 'Thai Tea', 'price' => 15000, 'stock' => 10],
                            ]
                        ],
                        [
                            'name' => 'Snacks',
                            'products' => [
                                ['name' => 'French Fries', 'price' => 12000, 'stock' => 10],
                                ['name' => 'Croissant', 'price' => 15000, 'stock' => 10],
                            ]
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Warung Makan (FnB)',
                'category_type' => 'FnB',
                'description' => 'Template untuk warung makan, depot, atau restoran sederhana.',
                'data' => [
                    'categories' => [
                        [
                            'name' => 'Makanan Utama',
                            'products' => [
                                ['name' => 'Nasi Goreng Ayam', 'price' => 15000, 'stock' => 10],
                                ['name' => 'Mie Goreng Spesial', 'price' => 18000, 'stock' => 10],
                                ['name' => 'Ayam Bakar Madu', 'price' => 20000, 'stock' => 10],
                            ]
                        ],
                        [
                            'name' => 'Minuman',
                            'products' => [
                                ['name' => 'Es Teh Manis', 'price' => 5000, 'stock' => 10],
                                ['name' => 'Es Jeruk', 'price' => 7000, 'stock' => 10],
                                ['name' => 'Kopi Hitam', 'price' => 5000, 'stock' => 10],
                            ]
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Toko Baju (Retail)',
                'category_type' => 'Retail',
                'description' => 'Template untuk toko pakaian, butik, atau konveksi.',
                'data' => [
                    'categories' => [
                        [
                            'name' => 'Atasan',
                            'products' => [
                                [
                                    'name' => 'Kaos Polos Cotton Combed',
                                    'price' => 50000,
                                    'stock' => 10,
                                    'modifier_groups' => [
                                        [
                                            'name' => 'Ukuran',
                                            'required' => true,
                                            'min_select' => 1,
                                            'max_select' => 1,
                                            'modifiers' => [
                                                ['name' => 'S', 'price' => 0],
                                                ['name' => 'M', 'price' => 0],
                                                ['name' => 'L', 'price' => 0],
                                                ['name' => 'XL', 'price' => 5000],
                                            ]
                                        ]
                                    ]
                                ],
                                ['name' => 'Kemeja Flanel', 'price' => 120000, 'stock' => 10],
                            ]
                        ],
                        [
                            'name' => 'Bawahan',
                            'products' => [
                                ['name' => 'Celana Jeans Slim Fit', 'price' => 150000, 'stock' => 10],
                                ['name' => 'Celana Chino', 'price' => 135000, 'stock' => 10],
                            ]
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Toko Sepatu (Retail)',
                'category_type' => 'Retail',
                'description' => 'Template untuk toko sepatu olahraga, casual, atau formal.',
                'data' => [
                    'categories' => [
                        [
                            'name' => 'Running',
                            'products' => [
                                [
                                    'name' => 'Sepatu Lari Ultraboost',
                                    'price' => 250000,
                                    'stock' => 10,
                                    'modifier_groups' => [
                                        [
                                            'name' => 'Size (EU)',
                                            'required' => true,
                                            'min_select' => 1,
                                            'max_select' => 1,
                                            'modifiers' => [
                                                ['name' => '40', 'price' => 0],
                                                ['name' => '41', 'price' => 0],
                                                ['name' => '42', 'price' => 0],
                                                ['name' => '43', 'price' => 0],
                                                ['name' => '44', 'price' => 0],
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        [
                            'name' => 'Casual',
                            'products' => [
                                ['name' => 'Sneakers Canvas', 'price' => 120000, 'stock' => 10],
                                ['name' => 'Slip-on Shoes', 'price' => 95000, 'stock' => 10],
                            ]
                        ]
                    ]
                ]
            ]
        ];

        foreach ($templates as $template) {
            ProductTemplate::updateOrCreate(
                ['name' => $template['name']],
                $template
            );
        }
    }
}
