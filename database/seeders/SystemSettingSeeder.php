<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SystemSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            'page_help_center' => [
                'title' => 'Pusat Bantuan',
                'blocks' => [
                    [
                        'id' => Str::uuid(),
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Pusat Bantuan JagoKasir',
                            'subtitle' => 'Temukan jawaban untuk semua pertanyaan Anda seputar penggunaan aplikasi.',
                            'align' => 'center',
                            'bgColor' => 'bg-indigo-600'
                        ]
                    ],
                    [
                        'id' => Str::uuid(),
                        'type' => 'markdown',
                        'data' => [
                            'content' => "## Panduan Pengguna\nBerikut adalah beberapa panduan untuk membantu Anda memulai.\n\n- **Pendaftaran Akun**: Masukkan email dan data outlet Anda.\n- **Tambah Produk**: Kelola kategori dan varian produk dengan mudah.\n- **Laporan Harian**: Pantau omzet Anda secara real-time."
                        ]
                    ],
                    [
                        'id' => Str::uuid(),
                        'type' => 'accordion',
                        'data' => [
                            'items' => [
                                ['q' => 'Bagaimana cara reset password?', 'a' => 'Klik "Lupa Password" pada halaman login.'],
                                ['q' => 'Apakah data saya aman?', 'a' => 'Ya, kami menggunakan enkripsi standar industri untuk melindungi data Anda.'],
                            ]
                        ]
                    ]
                ]
            ],
            'page_contact' => [
                'title' => 'Kontak',
                'blocks' => [
                    [
                        'id' => Str::uuid(),
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Hubungi Kami',
                            'subtitle' => 'Kami siap mendengarkan masukan dan membantu kendala Anda.',
                            'align' => 'left',
                            'bgColor' => 'bg-amber-500'
                        ]
                    ],
                    [
                        'id' => Str::uuid(),
                        'type' => 'contact_info',
                        'data' => [
                            'email' => 'support@jagokasir.com',
                            'phone' => '+62 812 3456 7890',
                            'address' => 'Jl. Teknologi No. 123, Jakarta, Indonesia',
                            'whatsapp' => '6281234567890'
                        ]
                    ]
                ]
            ],
            'page_privacy_policy' => [
                'title' => 'Kebijakan Privasi',
                'blocks' => [
                    [
                        'id' => Str::uuid(),
                        'type' => 'markdown',
                        'data' => [
                            'content' => "# Kebijakan Privasi\n\n*Terakhir diperbarui: 20 Maret 2026*\n\n### 1. Pengumpulan Data\nKami mengumpulkan informasi transaksi untuk keperluan laporan keuangan Anda.\n\n### 2. Penggunaan Data\nData Anda tidak akan pernah dijual kepada pihak ketiga.\n\n### 3. Keamanan\nSistem kami diproteksi oleh firewall dan enkripsi SSL."
                        ]
                    ]
                ]
            ],
            'page_terms_conditions' => [
                'title' => 'Syarat & Ketentuan',
                'blocks' => [
                    [
                        'id' => Str::uuid(),
                        'type' => 'markdown',
                        'data' => [
                            'content' => "# Syarat & Ketentuan\n\n*Terakhir diperbarui: 20 Maret 2026*\n\nDengan menggunakan JagoKasir, Anda menyetujui seluruh ketentuan berikut:\n\n1. **Lisensi**: Anda diberikan hak non-eksklusif untuk menggunakan software kami.\n2. **Tanggung Jawab**: Kami tidak bertanggung jawab atas kerugian bisnis akibat kesalahan input user.\n3. **Pembayaran**: Biaya berlangganan bersifat non-refundable."
                        ]
                    ]
                ]
            ],
        ];

        foreach ($settings as $key => $value) {
            SystemSetting::set($key, $value);
        }
    }
}
