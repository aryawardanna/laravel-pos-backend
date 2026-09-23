<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Baris hasil hitung fisik stock opname untuk satu bahan baku.
 */
class StockOpnameItem extends Model
{
    use HasFactory;

    protected $table = 'stock_opname_items';

    protected $fillable = [
        'stock_opname_id',
        'bahan_baku_id',
        'system_stock',
        'physical_stock',
        'difference',
        'unit_price',
        'description',
    ];

    public function stockOpname()
    {
        return $this->belongsTo(StockOpname::class);
    }

    public function bahanBaku()
    {
        return $this->belongsTo(BahanBaku::class);
    }

    /** Hasil hitung lebih besar dari stok sistem. */
    public function isIncrease(): bool
    {
        return (float) $this->difference > 0;
    }

    /** Hasil hitung lebih kecil dari stok sistem. */
    public function isDecrease(): bool
    {
        return (float) $this->difference < 0;
    }

    /** Hasil hitung sama dengan stok sistem. */
    public function isMatch(): bool
    {
        return (float) $this->difference === 0.0;
    }
}
