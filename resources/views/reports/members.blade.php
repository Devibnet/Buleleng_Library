@extends('reports.layout')

@section('content')

    <!-- RINGKASAN STATISTIK ANGGOTA -->
    <table class="stats-grid">
        <tr>
            <td width="30%">
                <div class="stats-card">
                    <div class="stats-value">{{ number_format($total_new_members) }}</div>
                    <div class="stats-label">Total Penambahan Anggota</div>
                </div>
            </td>
            <td width="70%">
                <div class="stats-card" style="text-align: left;">
                    <div class="stats-label" style="margin-bottom: 4px;">Breakdown Kategori Pekerjaan / Status:</div>
                    <div style="font-size: 11px;">
                        @forelse($job_category_breakdown as $cat => $count)
                            <span class="badge badge-info" style="margin-right: 6px; margin-bottom: 4px;">
                                {{ $cat ?? 'LAINNYA' }}: {{ $count }} orang
                            </span>
                        @empty
                            <span style="color: #64748b;">Belum ada breakdown</span>
                        @endforelse
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- DAFTAR ANGGOTA BARU -->
    <div class="section-title">Daftar Anggota Terdaftar (Periode Ini)</div>
    <table class="table-data">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="15%">No Anggota</th>
                <th>Nama Lengkap</th>
                <th>Kategori Pekerjaan</th>
                <th>Instansi / Sekolah</th>
                <th>No Telepon</th>
                <th>Tgl Terdaftar</th>
            </tr>
        </thead>
        <tbody>
            @forelse($members_list as $index => $member)
                <tr>
                    <td align="center">{{ $index + 1 }}</td>
                    <td><strong>{{ $member->member_no }}</strong></td>
                    <td>{{ $member->name }}</td>
                    <td><span class="badge badge-info">{{ $member->job_category ?? '-' }}</span></td>
                    <td>{{ $member->institution_name ?? '-' }}</td>
                    <td>{{ $member->phone_number ?? '-' }}</td>
                    <td>{{ $member->created_at ? $member->created_at->format('d/m/Y H:i') : '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" align="center">Tidak ada data penambahan anggota</td></tr>
            @endforelse
        </tbody>
    </table>

@endsection
