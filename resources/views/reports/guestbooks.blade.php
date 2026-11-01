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
                <div class="section-title">Demografi Pekerjaan Pengunjung</div>
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
                            <tr><td colspan="2" align="center">Belum ada data pekerjaan</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
            <td width="50%" style="vertical-align: top; padding-left: 6px;">
                <div class="section-title">Tingkat Pendidikan Pengunjung</div>
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
                            <tr><td colspan="2" align="center">Belum ada data pendidikan</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <!-- DAFTAR LOG KUNJUNGAN BUKU TAMU -->
    <div class="section-title">Daftar Log Kunjungan Perpustakaan ({{ $visit_type_label ?? 'Semua Jenis' }})</div>
    <table class="table-data">
        <thead>
            <tr>
                <th width="4%">No</th>
                <th width="11%">Waktu (WITA)</th>
                <th width="8%">Jenis</th>
                <th>Nama Pengunjung / Instansi</th>
                <th width="15%">Pekerjaan / Pendidikan</th>
                <th width="17%">Keperluan</th>
                <th width="5%">L</th>
                <th width="5%">P</th>
                <th width="6%">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($guestbooks_list as $index => $item)
                <tr>
                    <td align="center">{{ $index + 1 }}</td>
                    <td>{{ $item->visit_date ? $item->visit_date->copy()->timezone($timezone ?? 'Asia/Makassar')->format('d/m/Y H:i') : '-' }}</td>
                    <td><span class="badge badge-info">{{ \App\Models\Guestbook::VISIT_TYPE_LABELS[$item->visit_type] ?? $item->visit_type }}</span></td>
                    <td>
                        @if($item->visit_type === 'GROUP')
                            <strong>{{ $item->institution_name }}</strong>
                            <br><small style="color: #64748b;">Ketua: {{ $item->visitor_name }}{{ $item->phone_number ? ', Telp. ' . $item->phone_number : '' }}</small>
                        @else
                            <strong>{{ $item->visitor_name }}</strong>
                            @if($item->member)
                                <br><small style="color: #64748b;">No. Anggota: {{ $item->member->member_no }}</small>
                            @elseif($item->institution_name)
                                <br><small style="color: #64748b;">Instansi: {{ $item->institution_name }}</small>
                            @endif
                        @endif
                    </td>
                    <td>
                        @if($item->visit_type === 'GROUP')
                            -
                        @else
                            {{ \App\Models\Guestbook::JOB_LABELS[$item->job] ?? ($item->job ? ucwords(strtolower($item->job)) : '-') }}
                            @if($item->education)
                                <br><small style="color: #64748b;">{{ \App\Models\Guestbook::EDUCATION_LABELS[$item->education] ?? $item->education }}</small>
                            @endif
                        @endif
                    </td>
                    <td>{{ $item->visit_purpose ?? '-' }}</td>
                    <td align="center">{{ $item->gender_male_count }}</td>
                    <td align="center">{{ $item->gender_female_count }}</td>
                    <td align="center"><strong>{{ $item->total_personnel }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="9" align="center">Tidak ada log kunjungan buku tamu</td></tr>
            @endforelse
        </tbody>
    </table>

@endsection
