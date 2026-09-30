<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Dropdown user di pojok kanan header.
 *
 * Isinya: waktu login terakhir ("Logged in ... ago"), Edit Profile, Logout.
 * Dropdown Messages & Notifications (lonceng) bawaan template sudah dihapus.
 */
class HeaderTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    /** Ambil hanya potongan dropdown user, bukan seluruh halaman. */
    private function dropdownUser(string $html): string
    {
        $mulai = strpos($html, '<li class="dropdown"><a href="#"');

        if ($mulai === false) {
            return '';
        }

        $selesai = strpos($html, '</li>', $mulai);

        return $selesai === false
            ? ''
            : substr($html, $mulai, $selesai - $mulai + strlen('</li>'));
    }

    private function headerHtml(User $user): string
    {
        return $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->getContent();
    }

    public function test_dropdown_menampilkan_waktu_login_terakhir(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-30 10:00:00'));

        $user = $this->admin();
        $user->forceFill(['last_login_at' => Carbon::now()->subMinutes(5)])->save();

        $dropdown = $this->dropdownUser($this->headerHtml($user->fresh()));

        $this->assertNotSame('', $dropdown, 'Dropdown user tidak ditemukan di header.');
        $this->assertStringContainsString('Logged in 5 minutes ago', $dropdown);
        $this->assertStringNotContainsString('5 min ago', $dropdown, 'Judul dropdown masih teks statis template.');
    }

    public function test_dropdown_tetap_terbaca_saat_waktu_login_belum_tercatat(): void
    {
        $dropdown = $this->dropdownUser($this->headerHtml($this->admin()));

        $this->assertNotSame('', $dropdown);
        $this->assertStringContainsString('Logged in', $dropdown);
    }

    public function test_login_menyimpan_waktu_login_terakhir(): void
    {
        $user = User::factory()->create([
            'email' => 'kasir@example.com',
            'password' => 'password',
            'role' => 'admin',
            'status' => 1,
            'last_login_at' => null,
        ]);

        $this->assertNull($user->last_login_at);

        $this->post(route('login'), [
            'email' => 'kasir@example.com',
            'password' => 'password',
        ]);

        $this->assertNotNull(
            $user->fresh()->last_login_at,
            'Waktu login terakhir tidak tersimpan setelah login.'
        );
    }

    public function test_login_gagal_tidak_mengubah_waktu_login_terakhir(): void
    {
        $lama = Carbon::parse('2026-09-01 08:00:00');

        $user = User::factory()->create([
            'email' => 'kasir@example.com',
            'password' => 'password',
            'role' => 'admin',
            'status' => 1,
            'last_login_at' => $lama,
        ]);

        $this->post(route('login'), [
            'email' => 'kasir@example.com',
            'password' => 'password-salah',
        ]);

        $this->assertTrue($lama->equalTo($user->fresh()->last_login_at));
    }

    public function test_dropdown_tidak_lagi_memuat_messages_dan_lonceng_notifikasi(): void
    {
        $html = $this->headerHtml($this->admin());

        $this->assertStringNotContainsString('dropdown-list-toggle', $html);
        $this->assertStringNotContainsString('message-toggle', $html);
        $this->assertStringNotContainsString('notification-toggle', $html);
        $this->assertStringNotContainsString('fa-envelope', $html);
        $this->assertStringNotContainsString('fa-bell', $html);
        $this->assertStringNotContainsString('Mark All As Read', $html);
        $this->assertStringNotContainsString('View All', $html);
        $this->assertStringNotContainsString('Welcome to Stisla template!', $html);
    }

    public function test_dropdown_tidak_lagi_memuat_menu_dummy(): void
    {
        $html = $this->headerHtml($this->admin());

        $this->assertStringNotContainsString('features-profile.html', $html);
        $this->assertStringNotContainsString('features-activities.html', $html);
        $this->assertStringNotContainsString('features-settings.html', $html);
        $this->assertStringNotContainsString('Activities', $html);
        $this->assertStringNotContainsString('>Settings', $html);
    }

    public function test_isi_dropdown_user_lengkap(): void
    {
        $dropdown = $this->dropdownUser($this->headerHtml($this->admin()));

        $this->assertStringContainsString('Logged in', $dropdown);
        $this->assertStringContainsString(route('profile.edit'), $dropdown);
        $this->assertStringContainsString('Edit Profile', $dropdown);
        $this->assertStringContainsString('Logout', $dropdown);
        $this->assertStringContainsString(route('logout'), $dropdown);
    }
}
