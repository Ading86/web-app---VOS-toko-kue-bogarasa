<?php

namespace App\Http\Controllers;

use App\Http\Requests\BranchRequest;
use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Product;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BranchController extends Controller
{
    public function index(Request $request)
    {
        $branches = Branch::query()->withCount('users')
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('nama_cabang', 'like', "%{$s}%")->orWhere('kode_cabang', 'like', "%{$s}%")))
            ->orderBy('kode_cabang')->paginate(15)->withQueryString();

        return view('branches.index', compact('branches'));
    }

    public function create()
    {
        return view('branches.form', ['branch' => new Branch(['status' => 'aktif'])]);
    }

    public function store(BranchRequest $request, ActivityLogService $audit)
    {
        $branch = DB::transaction(function () use ($request) {
            $branch = Branch::create($request->validated());

            // Siapkan baris stok (0) untuk semua produk di cabang baru.
            Product::query()->orderBy('id_produk')->pluck('id_produk')->each(fn ($pid) => BranchStock::createZeroRow($branch->getKey(), $pid));

            return $branch;
        });

        $audit->created('branch', $branch, "Menambah cabang {$branch->nama_cabang}");

        return redirect()->route('branches.index')->with('success', 'Cabang berhasil ditambahkan.');
    }

    public function edit(Branch $branch)
    {
        return view('branches.form', compact('branch'));
    }

    public function update(BranchRequest $request, Branch $branch, ActivityLogService $audit)
    {
        $data = $request->validated();

        if ($branch->is_pusat && $data['status'] !== 'aktif') {
            return back()->withInput()->with('error', 'Cabang Pusat tidak dapat dinonaktifkan karena menjadi sumber distribusi stok.');
        }

        $before = $branch->getAttributes();
        $branch->update($data);
        $audit->updated('branch', $branch, $before, "Mengubah data cabang {$branch->nama_cabang}");

        return redirect()->route('branches.index')->with('success', 'Data cabang diperbarui.');
    }

    public function toggle(Branch $branch, ActivityLogService $audit)
    {
        // Cabang Pusat adalah sumber distribusi: tidak boleh dinonaktifkan.
        if ($branch->is_pusat && $branch->status === 'aktif') {
            return back()->with('error', 'Cabang Pusat tidak dapat dinonaktifkan karena menjadi sumber distribusi stok.');
        }

        $before = $branch->getAttributes();
        $branch->update(['status' => $branch->status === 'aktif' ? 'nonaktif' : 'aktif']);
        $audit->updated('branch', $branch, $before, "Mengubah status cabang {$branch->nama_cabang} menjadi {$branch->status}");

        return back()->with('success', 'Status cabang diperbarui.');
    }
}
