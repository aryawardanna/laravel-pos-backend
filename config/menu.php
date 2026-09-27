<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Super Admin
    |--------------------------------------------------------------------------
    |
    | Nilai kolom `role` pada tabel users yang otomatis mendapat akses ke
    | seluruh menu & route, tanpa perlu permission satu per satu.
    |
    */

    'super_admin_role' => 'admin',

    /*
    |--------------------------------------------------------------------------
    | Kemampuan Default Setiap Modul
    |--------------------------------------------------------------------------
    |
    | Kemampuan (ability) yang dibuat untuk modul yang tidak menyebutkannya
    | secara eksplisit. Nama permission-nya adalah "<key>.<ability>",
    | contoh modul "sale" -> "sale.view", "sale.create".
    |
    */

    'abilities_default' => ['view', 'create', 'edit', 'delete'],

    /*
    |--------------------------------------------------------------------------
    | Hak Akses Awal Per Role
    |--------------------------------------------------------------------------
    |
    | Dipakai oleh MenuAccessSeeder untuk mengisi permission pertama kali.
    | Setelah itu, pengaturan dilakukan melalui menu "Akses Menu" (dinamis).
    | Gunakan '*' untuk akses penuh.
    |
    */

    'default_role_permissions' => [
        'admin' => '*',
        'staff' => [
            'dashboard.view',
            'sale.view', 'sale.create', 'sale.delete',
            'purchase.view', 'purchase.create', 'purchase.edit',
            'batch_bahan_baku.view',
            'kartu_stok.view',
            'stock_opname.view', 'stock_opname.create', 'stock_opname.edit',
            'laporan.penjualan.view', 'laporan.penjualan.export',
            'laporan.barang_masuk.view', 'laporan.barang_masuk.export',
            'laporan.stok.view', 'laporan.stok.export',
            'category.view', 'menu.view', 'bahan_baku.view', 'satuan.view', 'supplier.view',
        ],
        'user' => [
            'dashboard.view',
            'sale.view',
            'kartu_stok.view',
            'laporan.penjualan.view', 'laporan.stok.view',
            'category.view', 'menu.view', 'bahan_baku.view', 'satuan.view', 'supplier.view',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Daftar Menu
    |--------------------------------------------------------------------------
    |
    | Sumber tunggal untuk sidebar, halaman "Akses Menu", dan seeder.
    | Menambah modul/menu baru cukup di file ini — tidak perlu mengubah
    | blade, controller, atau route.
    |
    | Struktur entri:
    |   key        prefix permission, contoh "sale" -> "sale.view"
    |   label      judul yang ditampilkan
    |   icon       class icon (FontAwesome)
    |   route      nama route tujuan (menentukan juga URL untuk status active)
    |   abilities  daftar kemampuan modul (opsional, default abilities_default)
    |
    | Entri yang punya "items" ditampilkan sebagai dropdown; entri tanpa
    | "items" ditampilkan sebagai link biasa.
    |
    */

    'menus' => [

        [
            'key' => 'dashboard',
            'label' => 'Dashboard',
            'icon' => 'fa fa-home',
            'route' => 'home',
            'abilities' => ['view'],
        ],

        [
            'key' => 'transaksi',
            'label' => 'Transaksi',
            'icon' => 'fas fa-shopping-cart ml-0',
            'items' => [
                [
                    'key' => 'sale',
                    'label' => 'Penjualan / Kasir',
                    'icon' => 'fas fa-laptop',
                    'route' => 'sale.index',
                    'abilities' => ['view', 'create', 'delete'],
                ],
                [
                    'key' => 'purchase',
                    'label' => 'Pembelian',
                    'icon' => 'fas fa-money-bill',
                    'route' => 'purchase.index',
                ],
            ],
        ],

        [
            'key' => 'inventory',
            'label' => 'Inventory',
            'icon' => 'fas fa-warehouse ml-0',
            'items' => [
                [
                    'key' => 'batch_bahan_baku',
                    'label' => 'Batch / Lot Stok',
                    'icon' => 'fas fa-barcode',
                    'route' => 'batch_bahan_baku.index',
                    'abilities' => ['view'],
                ],
                [
                    'key' => 'kartu_stok',
                    'label' => 'Kartu Stok',
                    'icon' => 'fas fa-chart-line',
                    'route' => 'kartu_stok.index',
                    'abilities' => ['view'],
                ],
                [
                    'key' => 'stock_opname',
                    'label' => 'Stock Opname',
                    'icon' => 'far fa-clipboard',
                    'route' => 'stock_opname.index',
                ],
            ],
        ],

        [
            'key' => 'laporan',
            'label' => 'Laporan',
            'icon' => 'far fa-file-excel ml-0',
            'items' => [
                [
                    'key' => 'laporan.penjualan',
                    'label' => 'Penjualan',
                    'icon' => 'far fa-chart-bar',
                    'route' => 'laporan.penjualan.index',
                    'abilities' => ['view', 'export'],
                ],
                [
                    'key' => 'laporan.barang_masuk',
                    'label' => 'Barang Masuk',
                    'icon' => 'fas fa-box-open',
                    'route' => 'laporan.barang_masuk.index',
                    'abilities' => ['view', 'export'],
                ],
                [
                    'key' => 'laporan.stok',
                    'label' => 'Stok',
                    'icon' => 'fas fa-boxes',
                    'route' => 'laporan.stok.index',
                    'abilities' => ['view', 'export'],
                ],
            ],
        ],

        [
            'key' => 'master_data',
            'label' => 'Master Data',
            'icon' => 'fas fa-database ml-0',
            'items' => [
                [
                    'key' => 'category',
                    'label' => 'Kategori Menu',
                    'icon' => 'fas fa-layer-group',
                    'route' => 'category.index',
                ],
                [
                    'key' => 'menu',
                    'label' => 'Produk / Menu',
                    'icon' => 'fas fa-utensils',
                    'route' => 'menu.index',
                ],
                [
                    'key' => 'bahan_baku',
                    'label' => 'Bahan Baku',
                    'icon' => 'fa fa-list',
                    'route' => 'bahan_baku.index',
                ],
                [
                    'key' => 'satuan',
                    'label' => 'Satuan',
                    'icon' => 'fas fa-i-cursor',
                    'route' => 'satuan.index',
                ],
                [
                    'key' => 'supplier',
                    'label' => 'Supplier',
                    'icon' => 'fas fa-building',
                    'route' => 'supplier.index',
                ],
            ],
        ],

        [
            'key' => 'pengaturan',
            'label' => 'Pengaturan',
            'icon' => 'fas fa-cog ml-0',
            'items' => [
                [
                    'key' => 'user',
                    'label' => 'Users',
                    'icon' => 'fa fa-users',
                    'route' => 'user.index',
                ],
                [
                    'key' => 'role',
                    'label' => 'Roles',
                    'icon' => 'fas fa-user-tag',
                    'route' => 'role.index',
                ],
                [
                    'key' => 'menu_access',
                    'label' => 'Akses Menu',
                    'icon' => 'fas fa-user-shield',
                    'route' => 'menu-access.index',
                    'abilities' => ['view', 'update'],
                ],
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Modul Tanpa Menu
    |--------------------------------------------------------------------------
    |
    | Modul yang route-nya tetap dilindungi permission, tetapi tidak
    | ditampilkan di sidebar.
    |
    */

    'hidden_modules' => [
        'product' => [
            'label' => 'Products',
            'abilities' => ['view', 'create', 'edit', 'delete'],
        ],
    ],

];
