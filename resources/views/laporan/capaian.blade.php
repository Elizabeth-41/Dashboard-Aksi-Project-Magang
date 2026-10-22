<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Capaian Rencana Aksi Smart City Banjarmasin</title>
    <style>
        @page { margin: 18mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #1B2A2F; }
        h1 { font-size: 15pt; margin: 0 0 2px; color: #0B3B4D; }
        h2 { font-size: 11pt; margin: 18px 0 6px; color: #136477; border-bottom: 1px solid #C99A3C; padding-bottom: 3px; }
        .sub { color: #5B6B70; margin: 0 0 12px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { border: 1px solid #C9D3D6; padding: 4px 5px; vertical-align: top; }
        th { background: #0B3B4D; color: #fff; text-align: left; font-weight: bold; }
        td.angka, th.angka { text-align: right; }
        tr.total td { font-weight: bold; background: #F4F6F7; }
        .ringkasan td:first-child { width: 45%; background: #F4F6F7; }
        .kecil { font-size: 8pt; }
        .footer { position: fixed; bottom: -8mm; left: 0; right: 0; font-size: 7.5pt; color: #5B6B70; text-align: center; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    <div class="footer">Dashboard Rencana Aksi Smart City Kota Banjarmasin · Diskominfotik · dicetak {{ now()->translatedFormat('d F Y H:i') }}</div>

    <h1>Laporan Capaian Rencana Aksi Smart City</h1>
    <p class="sub">Kota Banjarmasin · {{ $judulFilter }}</p>

    <h2>Ringkasan</h2>
    <table class="ringkasan">
        <tr><td>Jumlah unit / OPD</td><td class="angka">{{ $data['ringkasan']['jumlah_unit'] }}</td></tr>
        <tr><td>Total kegiatan</td><td class="angka">{{ $data['ringkasan']['total'] }}</td></tr>
        <tr><td>Belum Mulai</td><td class="angka">{{ $data['ringkasan']['belum_mulai'] }}</td></tr>
        <tr><td>Berjalan</td><td class="angka">{{ $data['ringkasan']['berjalan'] }}</td></tr>
        <tr><td>Selesai</td><td class="angka">{{ $data['ringkasan']['selesai'] }} ({{ $data['ringkasan']['persentase'] }}%)</td></tr>
        <tr><td>Bukti diterima / menunggu / ditolak</td><td class="angka">{{ $data['ringkasan']['bukti_diterima'] }} / {{ $data['ringkasan']['bukti_menunggu'] }} / {{ $data['ringkasan']['bukti_ditolak'] }}</td></tr>
    </table>

    <h2 class="page-break">Capaian per Dimensi</h2>
    <table>
        <thead><tr><th>Dimensi</th><th class="angka">Total</th><th class="angka">Belum Mulai</th><th class="angka">Berjalan</th><th class="angka">Selesai</th><th class="angka">%</th><th class="angka">Bukti Diterima</th></tr></thead>
        <tbody>
        @foreach ($data['per_dimensi'] as $r)
            <tr><td>{{ $r['dimensi'] }}</td><td class="angka">{{ $r['total'] }}</td><td class="angka">{{ $r['belum_mulai'] }}</td><td class="angka">{{ $r['berjalan'] }}</td><td class="angka">{{ $r['selesai'] }}</td><td class="angka">{{ $r['persentase'] }}</td><td class="angka">{{ $r['bukti_diterima'] }}</td></tr>
        @endforeach
        <tr class="total"><td>Total</td><td class="angka">{{ $data['ringkasan']['total'] }}</td><td class="angka">{{ $data['ringkasan']['belum_mulai'] }}</td><td class="angka">{{ $data['ringkasan']['berjalan'] }}</td><td class="angka">{{ $data['ringkasan']['selesai'] }}</td><td class="angka">{{ $data['ringkasan']['persentase'] }}</td><td class="angka">{{ $data['ringkasan']['bukti_diterima'] }}</td></tr>
        </tbody>
    </table>

    <h2 class="page-break">Capaian per Tahun</h2>
    <table>
        <thead><tr><th>Tahun</th><th class="angka">Total</th><th class="angka">Belum Mulai</th><th class="angka">Berjalan</th><th class="angka">Selesai</th><th class="angka">%</th></tr></thead>
        <tbody>
        @foreach ($data['per_tahun'] as $r)
            <tr><td>{{ $r['tahun'] }}</td><td class="angka">{{ $r['total'] }}</td><td class="angka">{{ $r['belum_mulai'] }}</td><td class="angka">{{ $r['berjalan'] }}</td><td class="angka">{{ $r['selesai'] }}</td><td class="angka">{{ $r['persentase'] }}</td></tr>
        @endforeach
        </tbody>
    </table>

    <h2 class="page-break">Capaian per Unit / OPD</h2>
    <table>
        <thead><tr><th>Unit / OPD</th><th class="angka">Total</th><th class="angka">Belum Mulai</th><th class="angka">Berjalan</th><th class="angka">Selesai</th><th class="angka">%</th><th class="angka">Bukti Diterima</th></tr></thead>
        <tbody>
        @forelse ($data['per_unit'] as $r)
            <tr><td>{{ $r['dinas'] }}</td><td class="angka">{{ $r['total'] }}</td><td class="angka">{{ $r['belum_mulai'] }}</td><td class="angka">{{ $r['berjalan'] }}</td><td class="angka">{{ $r['selesai'] }}</td><td class="angka">{{ $r['persentase'] }}</td><td class="angka">{{ $r['bukti_diterima'] }}</td></tr>
        @empty
            <tr><td colspan="7">Tidak ada kegiatan untuk filter ini.</td></tr>
        @endforelse
        </tbody>
    </table>

    @if (count($data['kegiatan']) > 0)
        <h2 class="page-break">Daftar Kegiatan ({{ count($data['kegiatan']) }})</h2>
        <table class="kecil">
            <thead><tr><th>No</th><th>Dimensi</th><th>Unit</th><th>Kegiatan / Rencana Aksi</th><th>Jenis</th><th>Kategori</th><th>Tahun</th><th>Status</th><th class="angka">Bukti ✓</th></tr></thead>
            <tbody>
            @foreach ($data['kegiatan'] as $i => $r)
                <tr>
                    <td class="angka">{{ $i + 1 }}</td>
                    <td>{{ str_replace('Smart ', '', $r['dimensi']) }}</td>
                    <td>{{ $r['unit'] }}</td>
                    <td>{{ $r['judul'] }}@if ($r['program'])<br><span style="color:#5B6B70">{{ $r['program'] }}</span>@endif</td>
                    <td>{{ $r['jenis_keluaran'] }}</td>
                    <td>{{ $r['kategori'] }}</td>
                    <td>{{ $r['tahun'] }}</td>
                    <td>{{ $r['status'] }}</td>
                    <td class="angka">{{ $r['bukti_diterima'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
