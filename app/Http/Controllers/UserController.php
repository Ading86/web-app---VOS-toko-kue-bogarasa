<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()->with(['branch', 'roles'])
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")))
            ->when($request->role, fn ($q, $r) => $q->role($r))
            ->when($request->branch_id, fn ($q, $b) => $q->where('branch_id', $b))
            ->orderBy('name')
            ->paginate(15)->withQueryString();

        return view('users.index', [
            'users' => $users,
            'roles' => Role::orderBy('name')->get(),
            'branches' => Branch::orderBy('nama_cabang')->get(),
        ]);
    }

    public function create()
    {
        return view('users.form', $this->formData(new User(), null));
    }

    public function store(UserRequest $request, ActivityLogService $audit)
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'branch_id' => $data['role'] === 'Owner' ? null : $data['branch_id'],
                'is_active' => true,
                'must_change_password' => false,   // akun baru tidak dipaksa ganti password saat login pertama
            ]);
            $user->syncRoles([$data['role']]);

            return $user;
        });

        $audit->log('user', 'created', "Membuat akun {$user->email} (role {$data['role']})", $user, null,
            $user->getAttributes() + ['role' => $data['role']]);

        return redirect()->route('users.index')->with('success', 'Pengguna berhasil ditambahkan.');
    }

    public function edit(User $user)
    {
        return view('users.form', $this->formData($user, $user->getRoleNames()->first()));
    }

    public function update(UserRequest $request, User $user, ActivityLogService $audit)
    {
        $data = $request->validated();
        $newRole = $data['role'];
        $active = $request->boolean('is_active');

        if ($user->is($request->user()) && ! $active) {
            return back()->withInput()->with('error', 'Anda tidak dapat menonaktifkan akun sendiri.');
        }

        // Lindungi akses administratif: Owner aktif terakhir tidak boleh turun role / nonaktif.
        if ($user->is_active && $user->isOwner() && ($newRole !== 'Owner' || ! $active) && $this->activeOwners() <= 1) {
            return back()->withInput()->with('error', 'Tidak dapat mengubah Owner aktif terakhir. Buat Owner lain dahulu.');
        }

        $before = $user->getAttributes();
        $oldRoles = $user->getRoleNames()->all();

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'branch_id' => $newRole === 'Owner' ? null : $data['branch_id'],
            'is_active' => $active,
        ];
        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
            $payload['must_change_password'] = false;
        }

        DB::transaction(function () use ($user, $payload, $newRole) {
            $user->update($payload);
            $user->syncRoles([$newRole]);
        });

        $audit->updated('user', $user, $before, "Mengubah akun {$user->email}");
        if ($oldRoles !== [$newRole]) {
            $audit->log('permission', 'role_diubah', "Mengubah role {$user->email}", $user,
                ['role' => $oldRoles], ['role' => [$newRole]]);
        }

        return redirect()->route('users.index')->with('success', 'Pengguna berhasil diperbarui.');
    }

    public function toggle(Request $request, User $user, ActivityLogService $audit)
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun sendiri.');
        }
        if ($user->is_active && $user->isOwner() && $this->activeOwners() <= 1) {
            return back()->with('error', 'Tidak dapat menonaktifkan Owner aktif terakhir.');
        }

        $before = $user->getAttributes();
        $user->update(['is_active' => ! $user->is_active]);
        $audit->updated('user', $user, $before, ($user->is_active ? 'Mengaktifkan' : 'Menonaktifkan') . " akun {$user->email}");

        return back()->with('success', 'Status pengguna diperbarui.');
    }

    private function activeOwners(): int
    {
        return User::role('Owner')->where('is_active', true)->count();
    }

    private function formData(User $user, ?string $currentRole): array
    {
        return [
            'user' => $user,
            'currentRole' => $currentRole,
            'roles' => Role::orderBy('name')->get(),
            'branches' => Branch::where('status', 'aktif')->orderBy('nama_cabang')->get(),
        ];
    }
}
