<?php

namespace App\Models;

use App\Models\Concerns\HasCodeId;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasCodeId;

    protected $primaryKey = 'id_role';
}
