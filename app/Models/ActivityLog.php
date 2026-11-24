<?php

namespace App\Models;

use App\Models\Concerns\HasCodeId;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasCodeId;

    protected $primaryKey = 'id_log';

    public const UPDATED_AT = null;

    protected $table = 'activity_log';

    protected $fillable = [
        'user_id', 'branch_id', 'log_name', 'aktivitas', 'description', 'subject_type', 'subject_id',
        'nilai_sebelum', 'nilai_sesudah', 'ip_address', 'user_agent',
    ];

    protected $casts = ['nilai_sebelum' => 'array', 'nilai_sesudah' => 'array', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        // Audit log immutable: tidak boleh diubah atau dihapus lewat aplikasi.
        static::updating(fn () => throw new \LogicException('Audit log tidak boleh diubah.'));
        static::deleting(fn () => throw new \LogicException('Audit log tidak boleh dihapus.'));
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
