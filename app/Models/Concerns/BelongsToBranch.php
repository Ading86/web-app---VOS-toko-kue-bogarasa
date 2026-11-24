<?php

namespace App\Models\Concerns;

use App\Models\Branch;
use App\Models\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Model;

trait BelongsToBranch
{
    public static function bootBelongsToBranch(): void
    {
        static::addGlobalScope(new BranchScope());

        // Kasir tidak bisa menulis data ke cabang lain, apa pun isi request.
        static::creating(function (Model $model) {
            $user = auth()->user();

            if ($user && ! $user->isOwner()) {
                $model->branch_id = $user->branch_id;
            }
        });
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
