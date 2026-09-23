<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

    /** Selesai: stok bahan baku sudah dikurangi (FEFO per batch). */
    public const STATUS_COMPLETED = 1;

    /** Dibatalkan: stok dikembalikan ke batch semula (soft delete via status -1). */
    public const STATUS_CANCELLED = -1;

    protected $table = 'sales';

    protected $fillable = [
        'code',
        'sale_date',
        'subtotal',
        'discount',
        'tax',
        'total',
        'paid',
        'change_amount',
        'payment_method',
        'status',
        'description',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'sale_date' => 'date',
    ];

    /**
     * Baris menu yang terjual.
     */
    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * Jejak pemakaian bahan baku per batch (FEFO).
     */
    public function usages()
    {
        return $this->hasMany(SaleItemUsage::class);
    }

    /**
     * Riwayat pergerakan stok (kartu stok) dari penjualan ini.
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

    public function isCompleted(): bool
    {
        return (int) $this->status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return (int) $this->status === self::STATUS_CANCELLED;
    }
}
