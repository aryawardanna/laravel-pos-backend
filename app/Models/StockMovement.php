<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Baris kartu stok: satu pergerakan stok bahan baku (masuk / keluar / penyesuaian).
 */
class StockMovement extends Model
{
    use HasFactory;

    /** Stok masuk (mis. pembelian diterima). */
    public const TYPE_IN = 'in';

    /** Stok keluar (mis. pembatalan / koreksi pembelian, pemakaian bahan). */
    public const TYPE_OUT = 'out';

    /** Penyesuaian manual (mis. stock opname). */
    public const TYPE_ADJUSTMENT = 'adjustment';

    protected $table = 'stock_movements';

    protected $fillable = [
        'bahan_baku_id',
        'purchase_id',
        'purchase_item_id',
        'stock_opname_id',
        'stock_opname_item_id',
        'sale_id',
        'sale_item_id',
        'batch_code',
        'movement_date',
        'type',
        'quantity_in',
        'quantity_out',
        'balance',
        'unit_price',
        'reference',
        'description',
        'created_by',
    ];

    protected $casts = [
        'movement_date' => 'date',
    ];

    public function bahanBaku()
    {
        return $this->belongsTo(BahanBaku::class);
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function purchaseItem()
    {
        return $this->belongsTo(PurchaseItem::class);
    }

    public function stockOpname()
    {
        return $this->belongsTo(StockOpname::class);
    }

    public function stockOpnameItem()
    {
        return $this->belongsTo(StockOpnameItem::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function saleItem()
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Label jenis pergerakan untuk tampilan.
     */
    public function typeLabel(): string
    {
        if ($this->type === self::TYPE_IN) {
            return 'Masuk';
        }

        if ($this->type === self::TYPE_OUT) {
            return 'Keluar';
        }

        return 'Penyesuaian';
    }
}
