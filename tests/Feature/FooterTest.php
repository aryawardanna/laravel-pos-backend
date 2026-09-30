<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\MenuAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Branding footer: ketiga layout (app, auth, error) memakai kredit
 * WardevStudio, bukan lagi kredit template Stisla.
 *
 * Assertion dibatasi pada blok footer saja supaya perubahan lain di
 * halaman (mis. judul tab) tidak ikut membuat test ini gagal.
 */
class FooterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    /** Ambil hanya potongan <div class="simple-footer"> ... </div>. */
    private function simpleFooter(string $html): string
    {
        $mulai = strpos($html, '<div class="simple-footer');

        if ($mulai === false) {
            return '';
        }

        $selesai = strpos($html, '</div>', $mulai);

        return $selesai === false
            ? ''
            : substr($html, $mulai, $selesai - $mulai + strlen('</div>'));
    }

    /** Ambil hanya potongan <footer class="main-footer"> ... </footer>. */
    private function mainFooter(string $html): string
    {
        $mulai = strpos($html, '<footer class="main-footer">');

        if ($mulai === false) {
            return '';
        }

        $selesai = strpos($html, '</footer>', $mulai);

        return $selesai === false
            ? ''
            : substr($html, $mulai, $selesai - $mulai + strlen('</footer>'));
    }

    public function test_footer_utama_memakai_branding_wardevstudio(): void
    {
        $html = $this->actingAs($this->admin())
            ->get(route('home'))
            ->assertOk()
            ->getContent();

        $footer = $this->mainFooter($html);

        $this->assertNotSame('', $footer, 'Blok footer utama tidak ditemukan di halaman.');
        $this->assertStringContainsString('https://wardevstudio.com', $footer);
        $this->assertStringContainsString('WardevStudio', $footer);
        $this->assertStringContainsString('Copyright &copy; '.date('Y'), $footer);

        // Kredit template lama tidak boleh muncul lagi.
        $this->assertStringNotContainsString('nauval.in', $footer);
        $this->assertStringNotContainsString('Muhamad Nauval Azhar', $footer);
        $this->assertStringNotContainsString('Stisla', $footer);
    }

    public function test_footer_halaman_login_memakai_branding_wardevstudio(): void
    {
        $html = $this->get(route('login'))
            ->assertOk()
            ->getContent();

        $footer = $this->simpleFooter($html);

        $this->assertNotSame('', $footer, 'Blok footer halaman login tidak ditemukan.');
        $this->assertStringContainsString('https://wardevstudio.com', $footer);
        $this->assertStringContainsString('WardevStudio', $footer);
        $this->assertStringContainsString('Copyright &copy; '.date('Y'), $footer);
        $this->assertStringNotContainsString('Stisla', $footer);
    }

    public function test_footer_halaman_error_memakai_branding_wardevstudio(): void
    {
        $this->seed(MenuAccessSeeder::class);
        Role::findByName('user')->syncPermissions([]);

        $html = $this->actingAs(User::factory()->create(['role' => 'user']))
            ->get(route('home'))
            ->assertForbidden()
            ->getContent();

        $footer = $this->simpleFooter($html);

        $this->assertNotSame('', $footer, 'Blok footer halaman error tidak ditemukan.');
        $this->assertStringContainsString('https://wardevstudio.com', $footer);
        $this->assertStringContainsString('WardevStudio', $footer);
        $this->assertStringContainsString('Copyright &copy; '.date('Y'), $footer);
        $this->assertStringNotContainsString('Stisla', $footer);
    }
}
