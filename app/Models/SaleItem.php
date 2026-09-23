<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    use HasFactory;

    protected $table = 'sale_items';

    protected $fillable = [
        'sale_id',
        'menu_id',
        'quantity',
        'unit_price',
        'subtotal',
        'description',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }

    /**
     * Jejak batch bahan baku yang terpakai untuk item ini.
     */
    public function usages()
    {
        return $this->hasMany(SaleItemUsage::class);
    }
}
