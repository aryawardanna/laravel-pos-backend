<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Halaman "Edit Profile" yang diakses dari dropdown pojok kanan header.
 *
 * Yang dijaga di sini: hanya user yang login yang bisa membukanya, perubahan
 * data tersimpan pada dirinya sendiri, dan password lama tetap dipakai bila
 * field password dibiarkan kosong.
 */
class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create([
            'name' => 'Kasir Satu',
            'email' => 'kasir@example.com',
            'role' => 'admin',
        ]);
    }

    public function test_halaman_edit_profile_bisa_diakses_user_yang_login(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Edit Profile')
            ->assertSee($user->email);
    }

    public function test_halaman_edit_profile_tertutup_untuk_tamu(): void
    {
        $this->get(route('profile.edit'))
            ->assertRedirect(route('login'));
    }

    public function test_user_bisa_mengubah_nama_dan_email(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => 'Kasir Diubah',
                'email' => 'kasir.baru@example.com',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Kasir Diubah',
            'email' => 'kasir.baru@example.com',
            'updated_by' => $user->id,
        ]);
    }

    public function test_password_lama_tetap_dipakai_bila_dikosongkan(): void
    {
        $user = $this->user();
        $lama = $user->password;

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => 'Kasir Satu',
                'email' => 'kasir@example.com',
                'password' => '',
            ]);

        $this->assertSame($lama, $user->fresh()->password);
    }

    public function test_password_baru_disimpan_setelah_dikonfirmasi(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => 'Kasir Satu',
                'email' => 'kasir@example.com',
                'password' => 'rahasia-baru',
                'password_confirmation' => 'rahasia-baru',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('rahasia-baru', $user->fresh()->password));
    }

    public function test_email_milik_user_lain_ditolak(): void
    {
        $user = $this->user();
        User::factory()->create(['email' => 'dipakai@example.com']);

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => 'Kasir Satu',
                'email' => 'dipakai@example.com',
            ])
            ->assertSessionHasErrors('email');

        $this->assertSame('kasir@example.com', $user->fresh()->email);
    }

    public function test_email_sendiri_tidak_dianggap_duplikat(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => 'Kasir Satu',
                'email' => 'kasir@example.com',
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_konfirmasi_password_tidak_cocok_ditolak(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => 'Kasir Satu',
                'email' => 'kasir@example.com',
                'password' => 'rahasia-baru',
                'password_confirmation' => 'beda-beda',
            ])
            ->assertSessionHasErrors('password');
    }
}
