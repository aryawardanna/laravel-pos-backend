<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BahanBaku extends Model
{
    use HasFactory;

    protected $table = 'bahan_bakus';

    protected $fillable = [
        'name',
        'code',
        'satuan_id',
        'price',
        'stock',
        'min_stock',
        'description',
        'image',
        'status',
        'created_by',
        'updated_by',
    ];

    /**
     * Kode bahan baku dibuat otomatis: unik dan berurutan 5 digit (00001, 00002, ...).
     *
     * Nomor berikutnya diambil dari kode numerik tertinggi yang sudah ada — termasuk
     * bahan baku yang sudah dihapus (status -1) — sehingga kode tidak pernah dipakai ulang.
     * Kode lama yang bukan angka (mis. "GULA") tidak memengaruhi urutan kode baru.
     */
    public static function generateCode(): string
    {
        $lastNumber = static::query()
            ->whereNotNull('code')
            ->pluck('code')
            ->filter(fn ($code) => ctype_digit((string) $code))
            ->map(fn ($code) => (int) $code)
            ->max() ?? 0;

        return CodeNumber($lastNumber + 1, 5);
    }

    /**
     * Isi kode otomatis saat data dibuat tanpa kode (mis. dari form master bahan baku).
     * Kode yang dikirim eksplisit (import/legacy) tetap dipakai apa adanya.
     */
    protected static function booted(): void
    {
        static::creating(function (BahanBaku $bahanBaku) {
            if (blank($bahanBaku->code)) {
                $bahanBaku->code = static::generateCode();
            }
        });
    }

    public function satuan()
    {
        return $this->belongsTo(Satuan::class);
    }

    public function menus()
    {
        return $this->belongsToMany(Menu::class, 'menu_bahan_bakus')->withPivot('quantity');
    }

    /**
     * Baris pembelian bahan baku (setiap baris adalah satu batch/lot).
     */
    public function purchaseItems()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /**
     * Riwayat pergerakan stok (kartu stok).
     */
    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Baris hasil hitung fisik (stock opname).
     */
    public function stockOpnameItems()
    {
        return $this->hasMany(StockOpnameItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}