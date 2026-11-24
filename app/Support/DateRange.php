<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DateRange
{
    public const PRESETS = [
        'today' => 'Hari ini', 'yesterday' => 'Kemarin', '7d' => '7 hari terakhir', '30d' => '30 hari terakhir',
        'this_month' => 'Bulan ini', 'last_month' => 'Bulan sebelumnya', 'custom' => 'Rentang tanggal khusus',
    ];

    private function __construct(public readonly Carbon $start, public readonly Carbon $end, public readonly string $preset)
    {
    }

    public static function fromRequest(Request $request, string $default = '7d'): self
    {
        $preset = (string) $request->input('periode', $default);
        if (! isset(self::PRESETS[$preset])) {
            $preset = $default;
        }

        $now = Carbon::now();

        [$start, $end] = match ($preset) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            '30d' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'custom' => self::custom($request, $now),
            default => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
        };

        return new self($start, $end, $preset);
    }

    private static function custom(Request $request, Carbon $now): array
    {
        try {
            $start = Carbon::parse((string) $request->input('date_from'))->startOfDay();
            $end = Carbon::parse((string) $request->input('date_to'))->endOfDay();
        } catch (\Throwable) {
            return [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()];
        }

        if ($start->gt($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }
        if ($start->diffInDays($end) > 365) {
            $start = $end->copy()->subDays(365)->startOfDay();   // batasi agar query tetap ringan
        }

        return [$start, $end];
    }

    public function label(): string
    {
        return self::PRESETS[$this->preset] . ' (' . $this->start->format('d/m/Y') . ' – ' . $this->end->format('d/m/Y') . ')';
    }
}
