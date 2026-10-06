<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\Menu;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'created_by',
        'updated_by',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'integer',
        ];
    }

    /**
     * Scope a query to only include active users (status = 1).
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Samakan role Spatie dengan kolom `role` pada tabel users.
     *
     * Kolom `role` tetap menjadi sumber utama (dipakai form user & daftar
     * user), sementara tabel Spatie menyimpan permission tiap role.
     * Dijalankan oleh middleware SyncUserRole setiap request.
     */
    public function syncRoleFromColumn(): void
    {
        if (! $this->exists) {
            return;
        }

        $role = (string) ($this->role ?: 'user');

        if (in_array($role, $this->getRoleNames()->all(), true)) {
            return;
        }

        $this->syncRoles([Role::findOrCreate($role, $this->getDefaultGuardName())]);
    }

    /**
     * User ini super admin? (lihat config/menu.php)
     */
    public function isSuperAdmin(): bool
    {
        return app(Menu::class)->isSuperAdmin($this);
    }

    /**
     * User ini boleh membuka modul/permission tersebut?
     */
    public function canAccessMenu(string $permission): bool
    {
        return app(Menu::class)->can($this, $permission);
    }
}
