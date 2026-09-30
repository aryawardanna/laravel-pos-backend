<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Fitur "Remember Me" pada halaman login.
 *
 * Remember Me disimpan sebagai cookie "recaller" terpisah dari cookie sesi,
 * jadi bukti bahwa fitur ini aktif adalah keberadaan cookie tersebut pada
 * response login.
 */
class LoginRememberMeTest extends TestCase
{
    use RefreshDatabase;

    private function recallerCookieName(): string
    {
        return Auth::guard('web')->getRecallerName();
    }

    private function user(): User
    {
        return User::factory()->create([
            'email' => 'kasir@example.com',
            'password' => 'password',
            'role' => 'admin',
            'status' => 1,
        ]);
    }

    public function test_halaman_login_menyediakan_centang_remember_me(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('name="remember"', false)
            ->assertSee('Remember Me');
    }

    public function test_login_dengan_remember_me_membuat_cookie_recaller(): void
    {
        $this->user();

        $response = $this->post(route('login'), [
            'email' => 'kasir@example.com',
            'password' => 'password',
            'remember' => '1',
        ]);

        $this->assertAuthenticated();

        $cookie = $this->recallerCookieName();

        $this->assertNotNull(
            $response->getCookie($cookie),
            "Cookie Remember Me ({$cookie}) tidak dibuat saat centang dicentang."
        );
    }

    public function test_login_tanpa_remember_me_tidak_membuat_cookie_recaller(): void
    {
        $this->user();

        $response = $this->post(route('login'), [
            'email' => 'kasir@example.com',
            'password' => 'password',
        ]);

        $this->assertAuthenticated();

        $this->assertNull(
            $response->getCookie($this->recallerCookieName()),
            'Cookie Remember Me tidak boleh dibuat bila centang tidak dicentang.'
        );
    }

    public function test_remember_token_tersimpan_di_tabel_users(): void
    {
        $user = $this->user();

        $this->post(route('login'), [
            'email' => 'kasir@example.com',
            'password' => 'password',
            'remember' => '1',
        ]);

        $this->assertNotNull(
            $user->fresh()->remember_token,
            'Kolom remember_token tidak terisi setelah login dengan Remember Me.'
        );
    }
}
