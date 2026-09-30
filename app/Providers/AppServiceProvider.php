<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Menu;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Menu accessor ikut memakai cache per-request (Super admin & status
        // konfigurasi permission) supaya tidak query berulang di sidebar.
        $this->app->scoped(Menu::class, fn () => new Menu);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFour();

        $this->registerMenuAccess();
        $this->registerSidebarMenu();

        /*
         * Custom authentication callback: only allow login if user status = 1 (active).
         * The callback receives the login request and must return a user instance on success,
         * or null to let Fortify fall back to the default guard attempt.
         *
         * We first look up the user by email (or name), check their status, then verify the
         * password hash manually. This gives us full control over the status check before
         * the session is created.
         */
        Fortify::authenticateUsing(function ($request) {
            $credentials = $request->only(Fortify::username(), 'password');

            $user = User::where(Fortify::username(), $credentials[Fortify::username()])
                ->first();

            if (! $user) {
                return null;
            }

            // Status check: hanya user dengan status = 1 (active) yang boleh login
            if ($user->status !== 1) {
                throw ValidationException::withMessages([
                    Fortify::username() => ['Akun Anda tidak aktif. Hubungi administrator untuk mengaktifkan kembali.'],
                ]);
            }

            // Verifikasi password
            if (! Auth::validate($credentials)) {
                return null;
            }

            // Catat waktu login terakhir. Dipakai dropdown header pojok kanan
            // untuk menampilkan "Logged in ... ago". Hanya diisi saat login
            // dengan password, bukan saat sesi dipulihkan oleh Remember Me.
            $user->last_login_at = now();
            $user->save();

            return $user;
        });
    }

    /**
     * Hak akses menu dinamis (spatie/laravel-permission).
     *
     * - Super admin (config/menu.php) selalu boleh, walau permission-nya
     *   belum dibuat.
     * - Selama tabel permission masih kosong (instalasi baru yang belum
     *   di-seed), semua akses dibuka supaya user tidak terkunci.
     * - Selain itu, accessor permission yang didaftarkan Spatie yang
     *   menentukan, sehingga middleware "permission" dan sidebar selalu
     *   memakai aturan yang sama.
     */
    private function registerMenuAccess(): void
    {
        Gate::before(function ($user, string $ability) {
            if (! $user instanceof User) {
                return null;
            }

            $menu = $this->app->make(Menu::class);

            if ($menu->isSuperAdmin($user) || ! $menu->isConfigured()) {
                return true;
            }

            return null;
        });
    }

    /**
     * Siapkan menu sidebar yang sudah difilter sesuai hak akses user.
     */
    private function registerSidebarMenu(): void
    {
        View::composer('components.sidebar', function ($view) {
            $view->with('menuTree', $this->app->make(Menu::class)->tree(request()->user()));
        });
    }
}
