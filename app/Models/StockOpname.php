<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockOpname extends Model
{
    use HasFactory;

    /** Draft: masih bisa diubah, belum memengaruhi stok. */
    public const STATUS_DRAFT = 0;

    /** Selesai: selisih sudah diterapkan ke stok & kartu stok. */
    public const STATUS_FINAL = 1;

    /** Dibatalkan: penyesuaian dikembalikan (soft delete via status -1). */
    public const STATUS_CANCELLED = -1;

    protected $table = 'stock_opnames';

    protected $fillable = [
        'code',
        'opname_date',
        'status',
        'description',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'opname_date' => 'date',
    ];

    public function items()
    {
        return $this->hasMany(StockOpnameItem::class);
    }

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

    public function isFinal(): bool
    {
        return (int) $this->status === self::STATUS_FINAL;
    }

    public function isCancelled(): bool
    {
        return (int) $this->status === self::STATUS_CANCELLED;
    }
}
