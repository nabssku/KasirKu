<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $methods = [
            [
                'name' => 'Cash',
                'code' => 'cash',
                'category' => 'cash',
                'icon' => 'BanknotesIcon',
            ],
            [
                'name' => 'QRIS',
                'code' => 'qris',
                'category' => 'e-wallet',
                'icon' => 'QrCodeIcon',
            ],
            [
                'name' => 'Bank Transfer',
                'code' => 'bank_transfer',
                'category' => 'bank',
                'icon' => 'BuildingLibraryIcon',
            ],
            [
                'name' => 'Credit/Debit Card',
                'code' => 'card',
                'category' => 'card',
                'icon' => 'CreditCardIcon',
            ],
        ];

        foreach ($methods as $method) {
            PaymentMethod::updateOrCreate(
                ['code' => $method['code']],
                [
                    'id' => Str::uuid(),
                    'name' => $method['name'],
                    'category' => $method['category'],
                    'icon' => $method['icon'],
                    'is_active' => true,
                ]
            );
        }
    }
}
