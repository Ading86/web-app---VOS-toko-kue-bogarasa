<?php

namespace App\Models;

use App\Models\Concerns\HasCodeId;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasCodeId;

    protected $primaryKey = 'id_kategori';

    protected $fillable = ['nama_kategori', 'status'];

    public function products()
    {
        return $this->hasMany(Product::class, 'category_id');
    }
}
