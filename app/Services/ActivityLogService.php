<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ActivityLogService
{
    private const HIDDEN = [
        'password', 'password_confirmation', 'current_password', 'remember_token',
        'token', 'api_token', '_token', 'secret',
    ];

    public function log(
        string $logName,
        string $aktivitas,
        string $description,
        ?Model $subject = null,
        ?array $before = null,
        ?array $after = null,
        ?string $branchId = null,
        ?User $user = null,
    ): ActivityLog {
        $user ??= auth()->user();

        $branchId ??= $subject?->getAttribute('branch_id') ?? $user?->branch_id;

        return ActivityLog::create([
            'user_id' => $user?->getKey(),
            'branch_id' => $branchId,
            'log_name' => $logName,
            'aktivitas' => $aktivitas,
            'description' => Str::limit($description, 250, ''),
            'subject_type' => $subject?->getTable(),
            'subject_id' => $subject?->getKey(),
            'nilai_sebelum' => $this->clean($before),
            'nilai_sesudah' => $this->clean($after),
            'ip_address' => request()?->ip(),
            'user_agent' => Str::limit((string) request()?->userAgent(), 250, ''),
        ]);
    }

    public function created(string $logName, Model $model, string $description): ActivityLog
    {
        return $this->log($logName, 'created', $description, $model, null, $model->getAttributes());
    }

    /** @param array $before atribut sebelum update (dari $model->getAttributes()) */
    public function updated(string $logName, Model $model, array $before, string $description): ?ActivityLog
    {
        $changes = Arr::except($model->getChanges(), ['updated_at']);

        if (! $changes) {
            return null;
        }

        return $this->log($logName, 'updated', $description, $model, Arr::only($before, array_keys($changes)), $changes);
    }

    public function deleted(string $logName, Model $model, string $description): ActivityLog
    {
        return $this->log($logName, 'deleted', $description, $model, $model->getAttributes(), null);
    }

    public function clean(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        return Arr::except($data, self::HIDDEN);
    }
}
