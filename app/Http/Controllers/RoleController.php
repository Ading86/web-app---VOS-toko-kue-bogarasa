<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    private const SYSTEM_ROLES = ['Owner', 'Kasir'];

    public function index()
    {
        return view('roles.index', [
            'roles' => Role::with('permissions')->withCount('users')->orderBy('name')->get(),
            'permissions' => Permission::orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('roles.form', ['role' => new Role(), 'permissions' => Permission::orderBy('name')->get(), 'selected' => []]);
    }

    public function store(Request $request, ActivityLogService $audit)
    {
        $data = $this->validated($request, null);

        $role = DB::transaction(function () use ($data) {
            $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
            $role->syncPermissions($data['permissions'] ?? []);

            return $role;
        });

        $audit->log('permission', 'created', "Membuat role {$role->name}", $role, null,
            ['name' => $role->name, 'permissions' => $data['permissions'] ?? []]);

        return redirect()->route('roles.index')->with('success', 'Role berhasil dibuat.');
    }

    public function edit(Role $role)
    {
        if ($role->name === 'Owner') {
            return redirect()->route('roles.index')->with('error', 'Hak akses Owner terkunci dan tidak dapat diubah.');
        }

        return view('roles.form', [
            'role' => $role,
            'permissions' => Permission::orderBy('name')->get(),
            'selected' => $role->permissions->pluck('name')->all(),
        ]);
    }

    public function update(Request $request, Role $role, ActivityLogService $audit)
    {
        if ($role->name === 'Owner') {
            return redirect()->route('roles.index')->with('error', 'Hak akses Owner terkunci dan tidak dapat diubah.');
        }

        $data = $this->validated($request, $role);
        $old = $role->permissions->pluck('name')->sort()->values()->all();
        $oldName = $role->name;

        DB::transaction(function () use ($role, $data) {
            if (! in_array($role->name, self::SYSTEM_ROLES, true)) {
                $role->update(['name' => $data['name']]);
            }
            $role->syncPermissions($data['permissions'] ?? []);   // berlaku di request berikutnya (cache dibersihkan otomatis)
        });

        $new = collect($data['permissions'] ?? [])->sort()->values()->all();
        $audit->log('permission', 'updated', "Mengubah hak akses role {$oldName}", $role,
            ['permissions' => $old], ['permissions' => $new]);

        return redirect()->route('roles.index')->with('success', 'Hak akses role diperbarui.');
    }

    public function destroy(Role $role, ActivityLogService $audit)
    {
        if (in_array($role->name, self::SYSTEM_ROLES, true)) {
            return back()->with('error', 'Role sistem tidak dapat dihapus.');
        }
        if ($role->users()->count() > 0) {
            return back()->with('error', 'Role masih dipakai pengguna. Pindahkan penggunanya dahulu.');
        }

        $audit->deleted('permission', $role, "Menghapus role {$role->name}");
        $role->delete();

        return redirect()->route('roles.index')->with('success', 'Role dihapus.');
    }

    private function validated(Request $request, ?Role $role): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:30', 'regex:/^[\pL\pN _-]+$/u',
                Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role?->getKey())],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ]);
    }
}
