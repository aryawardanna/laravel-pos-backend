<?php

use App\Http\Controllers\BahanBakuController;
use App\Http\Controllers\BatchBahanBakuController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KartuStokController;
use App\Http\Controllers\LaporanBarangMasukController;
use App\Http\Controllers\LaporanPenjualanController;
use App\Http\Controllers\LaporanStokController;
use App\Http\Controllers\MenuAccessController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SatuanController;
use App\Http\Controllers\StockOpnameController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('pages.auth.login');
});

/*
|--------------------------------------------------------------------------
| Helper Route Resource + Permission
|--------------------------------------------------------------------------
|
| Setiap modul dilindungi middleware "permission" (spatie/laravel-permission)
| dengan nama permission "<key>.<ability>" sesuai config/menu.php, sehingga
| hak akses tiap modul bisa diatur dari halaman "Akses Menu".
|
*/
$resource = function (string $uri, string $controller, string $key, array $actions = ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']) {
    $definitions = [
        'index' => ['get', '', 'view'],
        'create' => ['get', 'create', 'create'],
        'store' => ['post', '', 'create'],
        'show' => ['get', '{id}', 'view'],
        'edit' => ['get', '{id}/edit', 'edit'],
        'update' => ['put', '{id}', 'edit'],
        'destroy' => ['delete', '{id}', 'delete'],
    ];

    foreach ($actions as $action) {
        [$method, $suffix, $ability] = $definitions[$action];

        Route::$method($uri.($suffix === '' ? '' : '/'.$suffix), [$controller, $action])
            ->name("{$uri}.{$action}")
            ->middleware("permission:{$key}.{$ability}");
    }
};

Route::middleware(['auth', 'sync.role'])->group(function () use ($resource) {
    Route::get('home', [DashboardController::class, 'index'])
        ->name('home')
        ->middleware('permission:dashboard.view');

    // Akses menu: atur permission tiap role
    Route::get('menu-access', [MenuAccessController::class, 'index'])
        ->name('menu-access.index')
        ->middleware('permission:menu_access.view');
    Route::put('menu-access', [MenuAccessController::class, 'update'])
        ->name('menu-access.update')
        ->middleware('permission:menu_access.update');

    // Users
    Route::get('user/data', [UserController::class, 'data'])
        ->name('user.data')
        ->middleware('permission:user.view');
    $resource('user', UserController::class, 'user');

    // Roles (dinamis: dibuat dari halaman Roles, hak aksesnya di Akses Menu)
    Route::get('role/data', [RoleController::class, 'data'])
        ->name('role.data')
        ->middleware('permission:role.view');
    $resource('role', RoleController::class, 'role', ['index', 'create', 'store', 'edit', 'update', 'destroy']);

    // Products
    $resource('product', ProductController::class, 'product');

    // Kategori Menu
    Route::get('category/data', [CategoryController::class, 'data'])
        ->name('category.data')
        ->middleware('permission:category.view');
    $resource('category', CategoryController::class, 'category');

    // Satuan
    Route::get('satuan/data', [SatuanController::class, 'data'])
        ->name('satuan.data')
        ->middleware('permission:satuan.view');
    $resource('satuan', SatuanController::class, 'satuan');

    // Supplier
    Route::get('supplier/data', [SupplierController::class, 'data'])
        ->name('supplier.data')
        ->middleware('permission:supplier.view');
    $resource('supplier', SupplierController::class, 'supplier');

    // Bahan Baku
    Route::get('bahan_baku/data', [BahanBakuController::class, 'data'])
        ->name('bahan_baku.data')
        ->middleware('permission:bahan_baku.view');
    $resource('bahan_baku', BahanBakuController::class, 'bahan_baku');

    // Produk / Menu (resep)
    Route::get('menu/data', [MenuController::class, 'data'])
        ->name('menu.data')
        ->middleware('permission:menu.view');
    $resource('menu', MenuController::class, 'menu');

    // Transaksi penjualan / kasir (jual menu -> kurangi bahan baku resep FEFO)
    Route::get('sale/data', [SaleController::class, 'data'])
        ->name('sale.data')
        ->middleware('permission:sale.view');
    Route::get('sale/menu-info/{id}', [SaleController::class, 'menuInfo'])
        ->name('sale.menu-info')
        ->middleware('permission:sale.view');
    // Struk / invoice untuk printer thermal
    Route::get('sale/{id}/print', [SaleController::class, 'printReceipt'])
        ->name('sale.print')
        ->middleware('permission:sale.view');
    $resource('sale', SaleController::class, 'sale', ['index', 'create', 'store', 'show', 'destroy']);

    // Pembelian bahan baku (setiap baris = satu batch/lot)
    Route::get('purchase/data', [PurchaseController::class, 'data'])
        ->name('purchase.data')
        ->middleware('permission:purchase.view');
    Route::post('purchase/{id}/receive', [PurchaseController::class, 'receive'])
        ->name('purchase.receive')
        ->middleware('permission:purchase.edit');
    $resource('purchase', PurchaseController::class, 'purchase');

    // Batch / lot stok bahan baku
    Route::get('batch_bahan_baku/data', [BatchBahanBakuController::class, 'data'])
        ->name('batch_bahan_baku.data')
        ->middleware('permission:batch_bahan_baku.view');
    Route::get('batch_bahan_baku', [BatchBahanBakuController::class, 'index'])
        ->name('batch_bahan_baku.index')
        ->middleware('permission:batch_bahan_baku.view');

    // Kartu stok bahan baku
    Route::get('kartu_stok/data', [KartuStokController::class, 'data'])
        ->name('kartu_stok.data')
        ->middleware('permission:kartu_stok.view');
    Route::get('kartu_stok', [KartuStokController::class, 'index'])
        ->name('kartu_stok.index')
        ->middleware('permission:kartu_stok.view');

    // Stock opname bahan baku
    Route::get('stock_opname/data', [StockOpnameController::class, 'data'])
        ->name('stock_opname.data')
        ->middleware('permission:stock_opname.view');
    Route::post('stock_opname/{id}/finalize', [StockOpnameController::class, 'finalize'])
        ->name('stock_opname.finalize')
        ->middleware('permission:stock_opname.edit');
    $resource('stock_opname', StockOpnameController::class, 'stock_opname');

    // Laporan penjualan (datatable + export excel + pdf)
    Route::get('laporan/penjualan/data', [LaporanPenjualanController::class, 'data'])
        ->name('laporan.penjualan.data')
        ->middleware('permission:laporan.penjualan.view');
    Route::get('laporan/penjualan/summary', [LaporanPenjualanController::class, 'summary'])
        ->name('laporan.penjualan.summary')
        ->middleware('permission:laporan.penjualan.view');
    Route::get('laporan/penjualan/print', [LaporanPenjualanController::class, 'print'])
        ->name('laporan.penjualan.print')
        ->middleware('permission:laporan.penjualan.view');
    Route::get('laporan/penjualan/export', [LaporanPenjualanController::class, 'export'])
        ->name('laporan.penjualan.export')
        ->middleware('permission:laporan.penjualan.export');
    Route::get('laporan/penjualan/pdf', [LaporanPenjualanController::class, 'pdf'])
        ->name('laporan.penjualan.pdf')
        ->middleware('permission:laporan.penjualan.export');
    Route::get('laporan/penjualan', [LaporanPenjualanController::class, 'index'])
        ->name('laporan.penjualan.index')
        ->middleware('permission:laporan.penjualan.view');

    // Laporan barang masuk (datatable + export excel + pdf browser)
    Route::get('laporan/barang-masuk/data', [LaporanBarangMasukController::class, 'data'])
        ->name('laporan.barang_masuk.data')
        ->middleware('permission:laporan.barang_masuk.view');
    Route::get('laporan/barang-masuk/summary', [LaporanBarangMasukController::class, 'summary'])
        ->name('laporan.barang_masuk.summary')
        ->middleware('permission:laporan.barang_masuk.view');
    Route::get('laporan/barang-masuk/print', [LaporanBarangMasukController::class, 'print'])
        ->name('laporan.barang_masuk.print')
        ->middleware('permission:laporan.barang_masuk.view');
    Route::get('laporan/barang-masuk/export', [LaporanBarangMasukController::class, 'export'])
        ->name('laporan.barang_masuk.export')
        ->middleware('permission:laporan.barang_masuk.export');
    Route::get('laporan/barang-masuk', [LaporanBarangMasukController::class, 'index'])
        ->name('laporan.barang_masuk.index')
        ->middleware('permission:laporan.barang_masuk.view');

    // Laporan stok bahan baku (datatable + export excel + pdf browser)
    Route::get('laporan/stok/data', [LaporanStokController::class, 'data'])
        ->name('laporan.stok.data')
        ->middleware('permission:laporan.stok.view');
    Route::get('laporan/stok/summary', [LaporanStokController::class, 'summary'])
        ->name('laporan.stok.summary')
        ->middleware('permission:laporan.stok.view');
    Route::get('laporan/stok/print', [LaporanStokController::class, 'print'])
        ->name('laporan.stok.print')
        ->middleware('permission:laporan.stok.view');
    Route::get('laporan/stok/export', [LaporanStokController::class, 'export'])
        ->name('laporan.stok.export')
        ->middleware('permission:laporan.stok.export');
    Route::get('laporan/stok', [LaporanStokController::class, 'index'])
        ->name('laporan.stok.index')
        ->middleware('permission:laporan.stok.view');
});
