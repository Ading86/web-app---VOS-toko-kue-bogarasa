<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $isOwner = $request->user()->isOwner();

        $base = Transaction::query()
            ->when($request->search, fn ($q, $s) => $q->where('kode_transaksi', 'like', "%{$s}%"))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->date_from, fn ($q, $d) => $q->whereDate('waktu_transaksi', '>=', $d))
            ->when($request->date_to, fn ($q, $d) => $q->whereDate('waktu_transaksi', '<=', $d))
            ->when($isOwner && $request->branch_id, fn ($q, $b) => $q->where('branch_id', $b));

        $valid = (clone $base)->where('status', Transaction::STATUS_SELESAI);

        return view('transactions.index', [
            'transactions' => (clone $base)->with(['cashier:id_user,name', 'branch:id_cabang,kode_cabang,nama_cabang'])
                ->orderByDesc('waktu_transaksi')->paginate(20)->withQueryString(),
            'sumOmzet' => (float) (clone $valid)->sum('total'),
            'sumCount' => (clone $valid)->count(),
            'branches' => $isOwner ? Branch::orderBy('nama_cabang')->get() : collect(),
        ]);
    }

    public function show(Transaction $transaction)
    {
        $transaction->load(['details', 'payments', 'cashier:id_user,name', 'branch', 'cancelledBy:id_user,name']);

        return view('transactions.show', ['trx' => $transaction]);
    }

    public function receipt(Transaction $transaction)
    {
        $transaction->load(['details', 'payments', 'cashier:id_user,name', 'branch']);

        return view('transactions.receipt', ['trx' => $transaction]);
    }

    public function cancel(Request $request, Transaction $transaction, TransactionService $service)
    {
        $data = $request->validate(['alasan' => ['required', 'string', 'min:3', 'max:150']]);

        $service->cancel($transaction, $request->user(), $data['alasan']);

        return redirect()->route('transactions.show', $transaction)->with('success', 'Transaksi dibatalkan dan stok dikembalikan.');
    }
}
