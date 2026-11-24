<?php

namespace App\Models\Concerns;

use App\Services\CodeGeneratorService;
use App\Support\IdFormat;

/**
 * ID berbentuk PREFIX + digit tetap (mis. USR001). Prefix dan jumlah digit diambil dari
 * App\Support\IdFormat berdasarkan nama tabel model, sehingga hanya ada satu sumber kebenaran.
 */
trait HasCodeId
{
    public function codePrefix(): string
    {
        return IdFormat::prefixOf($this->getTable());
    }

    public function codePad(): int
    {
        return IdFormat::digitsOf($this->getTable());
    }

    public static function bootHasCodeId(): void
    {
        static::creating(function ($model) {
            $key = $model->getKeyName();

            if (empty($model->{$key})) {
                $model->{$key} = app(CodeGeneratorService::class)
                    ->next($model->codePrefix(), $model->codePad());
            }
        });
    }

    public function getIncrementing()
    {
        return false;
    }

    public function getKeyType()
    {
        return 'string';
    }

    public function getIdAttribute(): ?string
    {
        return $this->attributes[$this->getKeyName()] ?? null;
    }

    public function setIdAttribute($value): void
    {
        $this->attributes[$this->getKeyName()] = $value;
    }
}
