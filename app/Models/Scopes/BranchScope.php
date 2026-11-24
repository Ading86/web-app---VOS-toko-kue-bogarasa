<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class BranchScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        // Owner, console (seeder/artisan), dan guest tidak dibatasi di sini.
        if (! $user || $user->isOwner()) {
            return;
        }

        if ($user->branch_id === null) {
            $builder->whereRaw('1 = 0'); // kasir tanpa cabang: tidak melihat apa pun
            return;
        }

        $builder->where($model->qualifyColumn('branch_id'), $user->branch_id);
    }
}
