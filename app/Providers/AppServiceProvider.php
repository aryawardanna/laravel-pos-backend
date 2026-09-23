<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFour();

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

            return $user;
        });
    }
}
