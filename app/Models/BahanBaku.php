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

    public function satuan()
    {
        return $this->belongsTo(Satuan::class);
    }

    public function menus()
    {
        return $this->belongsToMany(Menu::class, 'menu_bahan_bakus')->withPivot('quantity');
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