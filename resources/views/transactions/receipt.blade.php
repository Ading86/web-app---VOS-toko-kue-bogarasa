<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Struk {{ $trx->kode_transaksi }}</title>
    <style>
        body { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 12px; color: #111; background: #f5f0e8; margin: 0; }
        .paper { width: 300px; margin: 16px auto; background: #fff; padding: 16px; position: relative; }
        .c { text-align: center; } .r { text-align: right; } hr { border: 0; border-top: 1px dashed #888; margin: 8px 0; }
        table { width: 100%; border-collapse: collapse; } td { padding: 2px 0; vertical-align: top; }
        .void { position: absolute; top: 40%; left: 10%; font-size: 36px; color: rgba(200,0,0,.35); transform: rotate(-20deg); font-weight: bold; }
        .actions { text-align: center; margin: 12px; } button, a.b { padding: 8px 14px; border: 1px solid #8b5a2f; background: #8b5a2f; color: #fff; border-radius: 6px; text-decoration: none; font: inherit; cursor: pointer; }
        a.b.alt { background: #fff; color: #8b5a2f; }
        @media print { body { background: #fff; } .actions { display: none; } .paper { margin: 0; width: 100%; } }
    </style>
</head>
<body>
<div class="actions"><button onclick="window.print()">Cetak</button> <a class="b alt" href="{{ route('transactions.show', $trx) }}">Detail</a>
    @can('transactions.create')<a class="b alt" href="{{ route('pos.index') }}">Transaksi baru</a>@endcan</div>
<div class="paper">
    @if ($trx->status === 'dibatalkan')<div class="void">DIBATALKAN</div>@endif
    <div class="c"><strong>BOGARASA</strong><br>{{ $trx->branch?->nama_cabang }}<br>{{ $trx->branch?->alamat }}<br>{{ $trx->branch?->nomor_telepon }}</div>
    <hr>
    <table>
        <tr><td>No</td><td class="r">{{ $trx->kode_transaksi }}</td></tr>
        <tr><td>Tanggal</td><td class="r">{{ $trx->waktu_transaksi->format('d/m/Y H:i') }}</td></tr>
        <tr><td>Kasir</td><td class="r">{{ $trx->cashier?->name }}</td></tr>
    </table>
    <hr>
    <table>
        @foreach ($trx->details as $d)
            <tr><td colspan="2">{{ $d->nama_produk }}</td></tr>
            <tr><td>{{ $d->jumlah }} x {{ number_format($d->harga_satuan, 0, ',', '.') }}</td><td class="r">{{ number_format($d->subtotal, 0, ',', '.') }}</td></tr>
        @endforeach
    </table>
    <hr>
    <table>
        <tr><td><strong>TOTAL</strong></td><td class="r"><strong>Rp {{ number_format($trx->total, 0, ',', '.') }}</strong></td></tr>
        @foreach ($trx->payments as $p)
            <tr><td>Bayar ({{ strtoupper($p->metode) }})</td><td class="r">{{ number_format($p->jumlah_bayar, 0, ',', '.') }}</td></tr>
            <tr><td>Kembali</td><td class="r">{{ number_format($p->kembalian, 0, ',', '.') }}</td></tr>
        @endforeach
    </table>
    <hr>
    <div class="c">Terima kasih<br>Selamat menikmati!</div>
</div>
</body>
</html>
