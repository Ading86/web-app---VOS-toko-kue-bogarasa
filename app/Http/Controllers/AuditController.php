<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $logs = ActivityLog::query()->with(['user:id_user,name,email', 'branch:id_cabang,kode_cabang,nama_cabang'])
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('description', 'like', "%{$s}%")->orWhere('subject_id', 'like', "%{$s}%")))
            ->when($request->user_id, fn ($q, $v) => $q->where('user_id', $v))
            ->when($request->branch_id, fn ($q, $v) => $q->where('branch_id', $v))
            ->when($request->log_name, fn ($q, $v) => $q->where('log_name', $v))
            ->when($request->aktivitas, fn ($q, $v) => $q->where('aktivitas', $v))
            ->when($request->date_from, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->date_to, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->orderByDesc('created_at')->orderByDesc('id_log')
            ->paginate(25)->withQueryString();

        return view('audit.index', [
            'logs' => $logs,
            'users' => User::orderBy('name')->get(['id_user', 'name']),
            'branches' => Branch::orderBy('nama_cabang')->get(['id_cabang', 'nama_cabang']),
            'logNames' => ActivityLog::query()->distinct()->orderBy('log_name')->pluck('log_name'),
            'aktivitasList' => ActivityLog::query()->distinct()->orderBy('aktivitas')->pluck('aktivitas'),
        ]);
    }

    public function show(ActivityLog $activityLog)
    {
        $activityLog->load(['user:id_user,name,email', 'branch:id_cabang,kode_cabang,nama_cabang']);

        $before = $activityLog->nilai_sebelum ?? [];
        $after = $activityLog->nilai_sesudah ?? [];

        return view('audit.show', ['log' => $activityLog, 'keys' => collect(array_keys($before))->merge(array_keys($after))->unique()->values(), 'before' => $before, 'after' => $after]);
    }
}
