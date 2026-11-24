<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Services\DashboardService;
use App\Support\DateRange;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardService $service)
    {
        $user = $request->user();
        $range = DateRange::fromRequest($request);

        return view('dashboard.index', [
            'data' => $service->build($user, $range, $request->input('branch_id')),
            'isOwner' => $user->isOwner(),
            'periode' => $range->preset,
            'presets' => DateRange::PRESETS,
            'dateFrom' => $range->start->format('Y-m-d'),
            'dateTo' => $range->end->format('Y-m-d'),
            'branches' => $user->isOwner() ? Branch::orderBy('nama_cabang')->get(['id_cabang', 'nama_cabang']) : collect(),
            'live' => $user->can('vos.view'),
        ]);
    }

    public function data(Request $request, DashboardService $service)
    {
        return response()->json($service->build($request->user(), DateRange::fromRequest($request), $request->input('branch_id')));
    }

    public function version(Request $request, DashboardService $service)
    {
        return response()->json(['version' => $service->version($request->user(), $request->input('branch_id'))]);
    }
}
