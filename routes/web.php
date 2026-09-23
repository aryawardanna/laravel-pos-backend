<?php

use App\Http\Controllers\BahanBakuController;
use App\Http\Controllers\BatchBahanBakuController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\KartuStokController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SatuanController;
use App\Http\Controllers\StockOpnameController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use App\Models\Category;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('pages.auth.login');
});

Route::middleware(['auth'])->group(function () {
    Route::get('home', function () {
        return view('pages.dashboard');
    })->name('home');

    Route::get('user/data', [UserController::class, 'data'])->name('user.data');
    Route::resource('user', UserController::class);
    Route::resource('product', ProductController::class);

    Route::get('category/data', [CategoryController::class, 'data'])->name('category.data');
    Route::resource('category', CategoryController::class);

    Route::get('satuan/data', [SatuanController::class, 'data'])->name('satuan.data');
    Route::resource('satuan', SatuanController::class);

    Route::get('supplier/data', [SupplierController::class, 'data'])->name('supplier.data');
    Route::resource('supplier', SupplierController::class);

    Route::get('bahan_baku/data', [BahanBakuController::class, 'data'])->name('bahan_baku.data');
    Route::resource('bahan_baku', BahanBakuController::class);

    Route::get('menu/data', [MenuController::class, 'data'])->name('menu.data');
    Route::resource('menu', MenuController::class);

    // Transaksi penjualan / kasir (jual menu -> kurangi bahan baku resep FEFO)
    Route::get('sale/data', [SaleController::class, 'data'])->name('sale.data');
    Route::get('sale/menu-info/{id}', [SaleController::class, 'menuInfo'])->name('sale.menu-info');
    // Struk / invoice untuk printer thermal
    Route::get('sale/{id}/print', [SaleController::class, 'printReceipt'])->name('sale.print');
    Route::resource('sale', SaleController::class)->only(['index', 'create', 'store', 'show', 'destroy']);

    // Pembelian bahan baku (setiap baris = satu batch/lot)
    Route::get('purchase/data', [PurchaseController::class, 'data'])->name('purchase.data');
    Route::post('purchase/{id}/receive', [PurchaseController::class, 'receive'])->name('purchase.receive');
    Route::resource('purchase', PurchaseController::class);

    // Batch / lot stok bahan baku
    Route::get('batch_bahan_baku/data', [BatchBahanBakuController::class, 'data'])->name('batch_bahan_baku.data');
    Route::get('batch_bahan_baku', [BatchBahanBakuController::class, 'index'])->name('batch_bahan_baku.index');

    // Kartu stok bahan baku
    Route::get('kartu_stok/data', [KartuStokController::class, 'data'])->name('kartu_stok.data');
    Route::get('kartu_stok', [KartuStokController::class, 'index'])->name('kartu_stok.index');

    // Stock opname bahan baku
    Route::get('stock_opname/data', [StockOpnameController::class, 'data'])->name('stock_opname.data');
    Route::post('stock_opname/{id}/finalize', [StockOpnameController::class, 'finalize'])->name('stock_opname.finalize');
    Route::resource('stock_opname', StockOpnameController::class);
});
