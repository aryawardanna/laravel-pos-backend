<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Identitas Toko (dicetak pada struk / invoice)
    |--------------------------------------------------------------------------
    */

    'store_name' => env('POS_STORE_NAME', 'DISENJA'),
    'store_address' => env('POS_STORE_ADDRESS', ''),
    'store_phone' => env('POS_STORE_PHONE', ''),
    'receipt_footer' => env('POS_RECEIPT_FOOTER', 'Terima kasih atas kunjungan Anda'),

    /*
    |--------------------------------------------------------------------------
    | Printer Thermal
    |--------------------------------------------------------------------------
    | thermal_paper_width  : lebar kertas printer thermal dalam mm (58 atau 80).
    |                        Dipakai untuk @page size dan lebar struk saat dicetak.
    | thermal_paper_height : tinggi kertas dalam mm. 297 = cocok dengan preset
    |                        driver thermal yang paling umum (Roll Paper 80 x 297 mm)
    |                        sehingga browser tidak jatuh ke A4.
    |                        0 = tinggi mengikuti isi struk (hemat kertas, tapi
    |                        ukuran kertas terdeteksi sebagai "custom").
    | thermal_auto_print   : otomatis memanggil dialog print saat struk dibuka.
    | chars_per_line       : jumlah karakter per baris (58mm = 32, 80mm = 48),
    |                        dipakai untuk merapikan teks struk.
    |
    | Catatan: CSS @page hanya menerima <length> (mis. "80mm 297mm") atau kata
    | kunci "auto" satu-satunya. Menulis "80mm auto" membuat aturan itu tidak
    | valid sehingga browser memakai ukuran default printer (A4).
    */

    'thermal_paper_width' => (int) env('POS_THERMAL_PAPER_WIDTH', 80),
    'thermal_paper_height' => (int) env('POS_THERMAL_PAPER_HEIGHT', 297),
    'thermal_auto_print' => filter_var(env('POS_THERMAL_AUTO_PRINT', true), FILTER_VALIDATE_BOOLEAN),
    'chars_per_line' => [
        58 => 32,
        80 => 48,
    ],

];
