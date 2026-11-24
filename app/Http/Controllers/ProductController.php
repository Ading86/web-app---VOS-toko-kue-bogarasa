<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Category;
use App\Models\Product;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::query()->with('category')
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('nama_produk', 'like', "%{$s}%")->orWhere('kode_produk', 'like', "%{$s}%")))
            ->when($request->category_id, fn ($q, $c) => $q->where('category_id', $c))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderBy('nama_produk')->paginate(15)->withQueryString();

        return view('products.index', [
            'products' => $products,
            'categories' => Category::orderBy('nama_kategori')->get(),
        ]);
    }

    public function create()
    {
        return view('products.form', ['product' => new Product(['status' => 'aktif']), 'categories' => $this->activeCategories()]);
    }

    public function store(ProductRequest $request, ActivityLogService $audit)
    {
        $data = $request->safe()->except(['gambar', 'hapus_gambar']);
        $path = $request->file('gambar')?->store('products', 'public');

        $product = DB::transaction(function () use ($data, $path) {
            $product = Product::create($data + ['gambar' => $path]);

            // Stok awal 0 untuk setiap cabang; stok dikelola terpisah per cabang.
            Branch::query()->orderBy('id_cabang')->pluck('id_cabang')->each(fn ($bid) => BranchStock::createZeroRow($bid, $product->getKey()));

            return $product;
        });

        $audit->created('product', $product, "Menambah produk {$product->nama_produk}");

        return redirect()->route('products.index')->with('success', 'Produk berhasil ditambahkan.');
    }

    public function edit(Product $product)
    {
        return view('products.form', ['product' => $product, 'categories' => $this->activeCategories($product->category_id)]);
    }

    public function update(ProductRequest $request, Product $product, ActivityLogService $audit)
    {
        $before = $product->getAttributes();
        $data = $request->safe()->except(['gambar', 'hapus_gambar']);
        if (empty($data['kode_produk'])) {
            $data['kode_produk'] = $product->kode_produk;
        }

        $oldImage = $product->gambar;
        if ($request->hasFile('gambar')) {
            $data['gambar'] = $request->file('gambar')->store('products', 'public');
        } elseif ($request->boolean('hapus_gambar')) {
            $data['gambar'] = null;
        }

        $product->update($data);

        if ($oldImage && $oldImage !== $product->gambar) {
            Storage::disk('public')->delete($oldImage);
        }

        $audit->updated('product', $product, $before, "Mengubah produk {$product->nama_produk}");

        return redirect()->route('products.index')->with('success', 'Produk diperbarui.');
    }

    public function toggle(Product $product, ActivityLogService $audit)
    {
        $before = $product->getAttributes();
        $product->update(['status' => $product->status === 'aktif' ? 'nonaktif' : 'aktif']);
        $audit->updated('product', $product, $before, "Mengubah status produk {$product->nama_produk} menjadi {$product->status}");

        return back()->with('success', 'Status produk diperbarui.');
    }

    /** Soft delete: riwayat transaksi tetap utuh (detail menyimpan snapshot nama dan harga). */
    public function destroy(Product $product, ActivityLogService $audit)
    {
        $audit->deleted('product', $product, "Menghapus (soft delete) produk {$product->nama_produk}");
        $product->delete();

        return redirect()->route('products.index')->with('success', 'Produk dihapus. Riwayat transaksi tetap tersimpan.');
    }

    private function activeCategories(?string $includeId = null)
    {
        return Category::where('status', 'aktif')->when($includeId, fn ($q) => $q->orWhere('id_kategori', $includeId))
            ->orderBy('nama_kategori')->get();
    }
}
