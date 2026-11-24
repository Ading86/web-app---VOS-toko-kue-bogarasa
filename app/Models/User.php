<?php

namespace App\Models;

use App\Models\Concerns\HasCodeId;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasCodeId, HasRoles, Notifiable;

    protected $primaryKey = 'id_user';

    protected $fillable = ['branch_id', 'name', 'email', 'password', 'is_active', 'must_change_password'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
        'must_change_password' => 'boolean',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function isOwner(): bool
    {
        return $this->hasRole('Owner');
    }

    public function isKasir(): bool
    {
        return $this->hasRole('Kasir');
    }

    public function isWarehouse(): bool
    {
        return $this->hasRole('Warehouse');
    }
}
