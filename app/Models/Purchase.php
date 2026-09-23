<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use HasFactory;

    /** Draft: belum diterima, belum memengaruhi stok. */
    public const STATUS_DRAFT = 0;

    /** Diterima: stok bahan baku & batch bertambah. */
    public const STATUS_RECEIVED = 1;

    /** Dibatalkan: stok dikembalikan, record tetap tersimpan (soft delete via status -1). */
    public const STATUS_CANCELLED = -1;

    protected $table = 'purchases';

    protected $fillable = [
        'code',
        'supplier_id',
        'purchase_date',
        'total',
        'description',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'purchase_date' => 'date',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Detail pembelian. Setiap baris mewakili satu batch/lot bahan baku.
     */
    public function items()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /**
     * Riwayat pergerakan stok (kartu stok) dari pembelian ini.
     */
    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isDraft(): bool
    {
        return (int) $this->status === self::STATUS_DRAFT;
    }

    public function isReceived(): bool
    {
        return (int) $this->status === self::STATUS_RECEIVED;
    }

    public function isCancelled(): bool
    {
        return (int) $this->status === self::STATUS_CANCELLED;
    }
}
