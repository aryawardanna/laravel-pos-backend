<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menjaga agar role Spatie selalu sama dengan kolom `role` pada tabel users.
 *
 * Dengan begitu, ketika role user diubah dari halaman Users, hak akses menunya
 * langsung ikut berubah tanpa perlu seeding ulang.
 */
class SyncUserRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            $user->syncRoleFromColumn();
        }

        return $next($request);
    }
}
