<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Baris pembelian bahan baku yang sekaligus berfungsi sebagai BATCH / LOT inventory.
 */
class PurchaseItem extends Model
{
    use HasFactory;

    protected $table = 'purchase_items';

    /** Batch berasal dari pembelian bahan baku. */
    public const SOURCE_PURCHASE = 'purchase';

    /** Lot penyesuaian: stok awal, stock opname, atau koreksi stok manual. */
    public const SOURCE_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'purchase_id',
        'source',
        'bahan_baku_id',
        'batch_code',
        'quantity',
        'remaining_qty',
        'unit_price',
        'subtotal',
        'expired_date',
        'description',
    ];

    protected $casts = [
        'expired_date' => 'date',
    ];

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function bahanBaku()
    {
        return $this->belongsTo(BahanBaku::class);
    }

    /**
     * Jumlah batch yang sudah terpakai.
     */
    public function usedQty(): float
    {
        return (float) $this->quantity - (float) $this->remaining_qty;
    }

    /**
     * Batch ini lot penyesuaian (bukan dari pembelian).
     */
    public function isAdjustment(): bool
    {
        return $this->source === self::SOURCE_ADJUSTMENT;
    }

    /**
     * Label sumber batch untuk tampilan.
     */
    public function sourceLabel(): string
    {
        return $this->isAdjustment() ? 'Penyesuaian' : 'Pembelian';
    }

    /**
     * Batch belum pernah terpakai sama sekali.
     */
    public function isUntouched(): bool
    {
        return (float) $this->remaining_qty === (float) $this->quantity;
    }
}
