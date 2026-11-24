<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Support\IdFormat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\BogarasaFixtures;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use BogarasaFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBogarasa();
    }

    public function test_package_uses_custom_string_key_models(): void
    {
        $this->assertSame(Role::class, config('permission.models.role'), 'Set config/permission.php models.role ke App\Models\Role');
        $this->assertSame(Permission::class, config('permission.models.permission'));
    }

    public function test_owner_passes_every_gate(): void
    {
        $this->assertTrue(Gate::forUser($this->owner())->allows('ability.yang.belum.ada'), 'Tambahkan Gate::before di AppServiceProvider');
    }

    public function test_seeded_ids_are_readable_varchar_codes(): void
    {
        $this->assertMatchesRegularExpression(IdFormat::patternOf('users'), $this->owner()->id);          // USR001
        $this->assertMatchesRegularExpression(IdFormat::patternOf('branches'), $this->branchA->id);       // BRN02
        $this->assertMatchesRegularExpression(IdFormat::patternOf('roles'), Role::first()->id);           // ROL01
        $this->assertMatchesRegularExpression(IdFormat::patternOf('permissions'), Permission::first()->id); // PRM001
    }

    public function test_kasir_cannot_open_admin_pages(): void
    {
        $k = $this->makeUser('Kasir', $this->branchA);
        foreach (['/users', '/branches', '/audit', '/roles', '/reports'] as $url) {
            $this->actingAs($k)->get($url)->assertForbidden();
        }
    }

    public function test_owner_can_open_admin_pages(): void
    {
        foreach (['/users', '/branches', '/audit', '/roles', '/reports', '/products', '/categories', '/inventory', '/distributions', '/distributions/create', '/transactions', '/dashboard'] as $url) {
            $this->actingAs($this->owner())->get($url)->assertOk();
        }
    }

    public function test_permission_changes_apply_on_next_request_without_code_change(): void
    {
        $k = $this->makeUser('Kasir', $this->branchA);
        $role = Role::where('name', 'Kasir')->first();

        $this->actingAs($k)->get('/transactions')->assertOk();
        $role->revokePermissionTo('transactions.view');
        $this->actingAs($k)->get('/transactions')->assertForbidden();
        $role->givePermissionTo('transactions.view');
        $this->actingAs($k)->get('/transactions')->assertOk();
    }

    public function test_kasir_cannot_cancel_by_default(): void
    {
        $this->assertFalse(Role::where('name', 'Kasir')->first()->hasPermissionTo('transactions.cancel'));
    }

    public function test_owner_role_is_locked(): void
    {
        $ownerRole = Role::where('name', 'Owner')->first();
        $this->actingAs($this->owner())->put("/roles/{$ownerRole->id}", ['name' => 'Owner', 'permissions' => []])
            ->assertRedirect(route('roles.index'));
        $this->assertSame(Permission::count(), $ownerRole->fresh()->permissions()->count());
    }

    public function test_last_active_owner_cannot_be_demoted_or_deactivated(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->post("/users/{$owner->id}/toggle")->assertSessionHas('error');
        $this->assertTrue($owner->fresh()->is_active);

        $this->actingAs($owner)->put("/users/{$owner->id}", [
            'name' => $owner->name, 'email' => $owner->email, 'role' => 'Kasir', 'branch_id' => $this->branchA->id, 'is_active' => 1,
        ])->assertSessionHas('error');
        $this->assertTrue($owner->fresh()->isOwner());
    }

    public function test_kasir_account_requires_branch(): void
    {
        $this->actingAs($this->owner())->post('/users', [
            'name' => 'X', 'email' => 'x@test.com', 'password' => 'Secret123', 'role' => 'Kasir',
        ])->assertSessionHasErrors('branch_id');
    }
}
