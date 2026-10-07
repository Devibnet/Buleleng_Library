@extends('reports.layout')

@section('content')

    <!-- RINGKASAN BACA DI TEMPAT -->
    <table class="stats-grid">
        <tr>
            <td width="25%">
                <div class="stats-card">
                    <div class="stats-value">{{ number_format($summary['total_readings']) }}</div>
                    <div class="stats-label">Total Aktivitas Baca di Tempat</div>
                </div>
            </td>
            <td width="25%">
                <div class="stats-card">
                    <div class="stats-value" style="color: #0284c7;">{{ number_format($summary['member_count']) }}</div>
                    <div class="stats-label">Pembaca Anggota</div>
                </div>
            </td>
            <td width="25%">
                <div class="stats-card">
                    <div class="stats-value" style="color: #d97706;">{{ number_format($summary['guest_count']) }}</div>
                    <div class="stats-label">Pembaca Non-Anggota</div>
                </div>
            </td>
            <td width="25%">
                <div class="stats-card">
                    <div class="stats-value" style="color: #16a34a;">{{ $summary['member_ratio'] }}</div>
                    <div class="stats-label">Rasio Pembaca Anggota</div>
                </div>
            </td>
        </tr>
    </table>

    <!-- TOP BUKU BACA DI TEMPAT -->
    <div class="section-title">Top 10 Buku Paling Sering Dibaca di Tempat (Onsite)</div>
    <table class="table-data">
        <thead>
            <tr>
                <th width="8%">No</th>
                <th>Judul Buku</th>
                <th>Pengarang</th>
                <th>ISBN</th>
                <th width="20%" align="center">Frekuensi Dibaca</th>
            </tr>
        </thead>
        <tbody>
            @forelse($top_read_collections as $index => $book)
                <tr>
                    <td align="center">{{ $index + 1 }}</td>
                    <td><strong>{{ $book->title }}</strong></td>
                    <td>{{ $book->author_main ?? '-' }}</td>
                    <td>{{ $book->isbn ?? '-' }}</td>
                    <td align="center"><strong>{{ $book->total_read }}x dibaca</strong></td>
                </tr>
            @empty
                <tr><td colspan="5" align="center">Belum ada data statistik baca di tempat</td></tr>
            @endforelse
        </tbody>
    </table>

    <!-- LOG DETAIL BACA DI TEMPAT -->
    <div class="section-title">Detail Log Aktivitas Baca di Tempat</div>
    <table class="table-data">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th>Tgl & Waktu Baca</th>
                <th>Tipe Pengunjung</th>
                <th>Nama Pembaca / No. Member</th>
                <th>Barcode Buku</th>
                <th>Judul Buku</th>
            </tr>
        </thead>
        <tbody>
            @forelse($readings_list as $index => $item)
                <tr>
                    <td align="center">{{ $index + 1 }}</td>
                    <td>{{ $item->read_at ? $item->read_at->format('d/m/Y H:i') : '-' }}</td>
                    <td>
                        @if($item->member_id)
                            <span class="badge badge-success">ANGGOTA</span>
                        @else
                            <span class="badge badge-warning">NON-ANGGOTA</span>
                        @endif
                    </td>
                    <td>
                        @if($item->member)
                            <strong>{{ $item->member->name }}</strong> ({{ $item->member->member_no }})
                        @elseif($item->guestbook)
                            <strong>{{ $item->guestbook->visitor_name }}</strong> (Guestbook #{{ $item->guestbook_id }})
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ $item->collection->barcode_no ?? '-' }}</td>
                    <td>{{ $item->collection->catalog->title ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" align="center">Tidak ada log aktivitas baca di tempat</td></tr>
            @endforelse
        </tbody>
    </table>

@endsection
