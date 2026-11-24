<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BogarasaFixtures;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use BogarasaFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBogarasa();
    }

    public function test_login_success(): void
    {
        $u = $this->makeUser('Kasir', $this->branchA);
        $this->post('/login', ['email' => $u->email, 'password' => 'Secret123'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($u);
        $this->assertDatabaseHas('activity_log', ['user_id' => $u->id, 'aktivitas' => 'login']);
    }

    public function test_login_is_throttled_after_five_failures(): void
    {
        $u = $this->makeUser('Kasir', $this->branchA);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $u->email, 'password' => 'salah'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => $u->email, 'password' => 'Secret123']);
        $this->assertGuest();
        $this->assertStringContainsString('Terlalu banyak', session('errors')->first('email'));
    }

    public function test_inactive_user_cannot_login(): void
    {
        $u = $this->makeUser('Kasir', $this->branchA, ['is_active' => false]);
        $this->post('/login', ['email' => $u->email, 'password' => 'Secret123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_must_change_password_blocks_other_pages(): void
    {
        $u = $this->makeUser('Kasir', $this->branchA, ['must_change_password' => true]);
        $this->actingAs($u)->get('/inventory')->assertRedirect(route('password.change'));
    }

    public function test_kasir_without_active_branch_is_logged_out(): void
    {
        $this->branchA->update(['status' => 'nonaktif']);
        $u = $this->makeUser('Kasir', $this->branchA);
        $this->actingAs($u)->get('/inventory')->assertRedirect(route('login'));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }
}
