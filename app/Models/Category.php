<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $table = 'categories';

    /** Jenis kategori untuk memisahkan cetak struk & bon dapur/bar. */
    public const TYPE_MAKANAN = 'makanan';

    public const TYPE_MINUMAN = 'minuman';

    public const TYPE_LAINNYA = 'lainnya';

    protected $fillable = [
        'name',
        'description',
        'image',
        'type',
        'updated_by',
        'status',
        'created_by',
    ];

    /**
     * Daftar jenis kategori yang valid (key => label).
     */
    public static function types(): array
    {
        return [
            self::TYPE_MAKANAN => 'Makanan',
            self::TYPE_MINUMAN => 'Minuman',
            self::TYPE_LAINNYA => 'Lainnya',
        ];
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
