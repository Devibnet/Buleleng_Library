@extends('reports.layout')

@section('content')

    <!-- RINGKASAN KUNJUNGAN BUKU TAMU -->
    <table class="stats-grid">
        <tr>
            <td width="25%">
                <div class="stats-card">
                    <div class="stats-value">{{ number_format($summary['total_entries']) }}</div>
                    <div class="stats-label">Total Transaksi / Registrasi</div>
                </div>
            </td>
            <td width="25%">
                <div class="stats-card">
                    <div class="stats-value" style="color: #1e3a8a;">{{ number_format($summary['total_visitors']) }}</div>
                    <div class="stats-label">Total Pengunjung (Personel)</div>
                </div>
            </td>
            <td width="25%">
                <div class="stats-card">
                    <div class="stats-value" style="color: #0284c7;">{{ number_format($summary['total_male']) }}</div>
                    <div class="stats-label">Pengunjung Laki-Laki</div>
                </div>
            </td>
            <td width="25%">
                <div class="stats-card">
                    <div class="stats-value" style="color: #db2777;">{{ number_format($summary['total_female']) }}</div>
                    <div class="stats-label">Pengunjung Perempuan</div>
                </div>
            </td>
        </tr>
    </table>

    <!-- AGREGASI PEKERJAAN & PENDIDIKAN (JSON BREAKDOWN) -->
    <table width="100%" style="border-collapse: collapse; margin-bottom: 15px;">
        <tr>
            <td width="50%" style="vertical-align: top; padding-right: 6px;">
                <div class="section-title">Aggregated Job Breakdown (Demografi Pekerjaan)</div>
                <table class="table-data">
                    <thead>
                        <tr>
                            <th>Kategori Pekerjaan</th>
                            <th width="30%" align="center">Jumlah Pengunjung</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($job_breakdown_sum as $job => $count)
                            <tr>
                                <td><strong>{{ $job }}</strong></td>
                                <td align="center"><strong>{{ number_format($count) }}</strong> orang</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" align="center">Belum ada data breakdown pekerjaan</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
            <td width="50%" style="vertical-align: top; padding-left: 6px;">
                <div class="section-title">Aggregated Education Breakdown (Tingkat Pendidikan)</div>
                <table class="table-data">
                    <thead>
                        <tr>
                            <th>Tingkat Pendidikan</th>
                            <th width="30%" align="center">Jumlah Pengunjung</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($education_breakdown_sum as $edu => $count)
                            <tr>
                                <td><strong>{{ $edu }}</strong></td>
                                <td align="center"><strong>{{ number_format($count) }}</strong> orang</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" align="center">Belum ada data breakdown pendidikan</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <!-- DAFTAR LOG KUNJUNGAN BUKU TAMU -->
    <div class="section-title">Daftar Log Kunjungan Perpustakaan</div>
    <table class="table-data">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th>Tgl Kunjungan</th>
                <th>Tipe Kunjungan</th>
                <th>Nama Pengunjung / Instansi</th>
                <th>Pria</th>
                <th>Wanita</th>
                <th>Total Personel</th>
                <th>Alamat</th>
            </tr>
        </thead>
        <tbody>
            @forelse($guestbooks_list as $index => $item)
                <tr>
                    <td align="center">{{ $index + 1 }}</td>
                    <td>{{ $item->visit_date ? $item->visit_date->format('d/m/Y H:i') : '-' }}</td>
                    <td><span class="badge badge-info">{{ $item->visit_type }}</span></td>
                    <td>
                        <strong>{{ $item->visitor_name }}</strong>
                        @if($item->institution_name)
                            <br><small style="color: #64748b;">Instansi: {{ $item->institution_name }}</small>
                        @endif
                    </td>
                    <td align="center">{{ $item->gender_male_count }}</td>
                    <td align="center">{{ $item->gender_female_count }}</td>
                    <td align="center"><strong>{{ $item->total_personnel }}</strong></td>
                    <td>{{ $item->address ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" align="center">Tidak ada log kunjungan buku tamu</td></tr>
            @endforelse
        </tbody>
    </table>

@endsection
