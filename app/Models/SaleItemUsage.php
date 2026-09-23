<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleItemUsage extends Model
{
    use HasFactory;

    protected $table = 'sale_item_usages';

    protected $fillable = [
        'sale_id',
        'sale_item_id',
        'menu_id',
        'bahan_baku_id',
        'purchase_item_id',
        'batch_code',
        'quantity',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function saleItem()
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }

    public function bahanBaku()
    {
        return $this->belongsTo(BahanBaku::class);
    }

    /**
     * Batch/lot yang dipakai (FEFO).
     */
    public function batch()
    {
        return $this->belongsTo(PurchaseItem::class, 'purchase_item_id');
    }
}
