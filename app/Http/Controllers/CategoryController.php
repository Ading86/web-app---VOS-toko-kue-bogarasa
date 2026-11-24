<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use App\Services\ActivityLogService;

class CategoryController extends Controller
{
    public function index()
    {
        return view('categories.index', [
            'categories' => Category::withCount('products')->orderBy('nama_kategori')->paginate(20),
        ]);
    }

    public function store(CategoryRequest $request, ActivityLogService $audit)
    {
        $category = Category::create($request->validated());
        $audit->created('category', $category, "Menambah kategori {$category->nama_kategori}");

        return back()->with('success', 'Kategori ditambahkan.');
    }

    public function edit(Category $category)
    {
        return view('categories.edit', compact('category'));
    }

    public function update(CategoryRequest $request, Category $category, ActivityLogService $audit)
    {
        $before = $category->getAttributes();
        $category->update($request->validated());
        $audit->updated('category', $category, $before, "Mengubah kategori {$category->nama_kategori}");

        return redirect()->route('categories.index')->with('success', 'Kategori diperbarui.');
    }

    public function destroy(Category $category, ActivityLogService $audit)
    {
        if ($category->products()->withTrashed()->exists()) {
            return back()->with('error', 'Kategori masih dipakai produk. Nonaktifkan saja, jangan dihapus.');
        }

        $audit->deleted('category', $category, "Menghapus kategori {$category->nama_kategori}");
        $category->delete();

        return back()->with('success', 'Kategori dihapus.');
    }
}
