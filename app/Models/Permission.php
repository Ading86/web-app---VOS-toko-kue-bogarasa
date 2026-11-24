<?php

namespace App\Models;

use App\Models\Concerns\HasCodeId;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    use HasCodeId;

    protected $primaryKey = 'id_permission';
}
