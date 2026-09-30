<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman depan "/" adalah pintu masuk aplikasi.
 *
 * Tamu melihat form login, sedangkan user yang sudah login langsung
 * diarahkan ke dashboard. Sebelumnya route ini selalu me-render form
 * login, sehingga mengetik "/" setelah login malah balik ke halaman login.
 */
class RootRouteTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_tamu_melihat_form_login_di_root(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Login')
            ->assertSee(route('login'), false);
    }

    public function test_user_yang_sudah_login_diarahkan_dari_root_ke_dashboard(): void
    {
        $this->actingAs($this->admin())
            ->get('/')
            ->assertRedirect(route('home'));
    }

    public function test_root_tidak_lagi_menampilkan_form_login_untuk_user_yang_sudah_login(): void
    {
        $html = $this->actingAs($this->admin())
            ->get('/')
            ->assertRedirect(route('home'))
            ->getContent();

        $this->assertStringNotContainsString('name="password"', $html);
        $this->assertStringNotContainsString('Remember Me', $html);
    }

    public function test_tujuan_redirect_dari_root_bisa_dibuka(): void
    {
        $this->actingAs($this->admin())
            ->get('/')
            ->assertRedirect(route('home'));

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Dashboard');
    }

    public function test_user_yang_sudah_login_tidak_ditampilkan_form_login_di_halaman_login(): void
    {
        $this->actingAs($this->admin())
            ->get(route('login'))
            ->assertRedirect(route('home'));
    }

    public function test_setelah_logout_root_kembali_menampilkan_form_login(): void
    {
        $this->actingAs($this->admin())
            ->post(route('logout'))
            ->assertRedirect('/');

        $this->assertGuest();

        $this->get('/')
            ->assertOk()
            ->assertSee('Remember Me');
    }
}
