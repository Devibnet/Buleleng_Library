@extends('reports.layout')

@section('content')

    <!-- RINGKASAN KATALOG & KOLEKSI -->
    <table class="stats-grid">
        <tr>
            <td width="20%">
                <div class="stats-card">
                    <div class="stats-value">{{ number_format($summary['total_catalog_titles']) }}</div>
                    <div class="stats-label">Total Judul Katalog</div>
                </div>
            </td>
            <td width="20%">
                <div class="stats-card">
                    <div class="stats-value" style="color: #0284c7;">{{ number_format($summary['total_collections']) }}</div>
                    <div class="stats-label">Total Eksemplar</div>
                </div>
            </td>
            <td width="20%">
                <div class="stats-card">
                    <div class="stats-value" style="color: #16a34a;">{{ number_format($summary['total_available']) }}</div>
                    <div class="stats-label">Koleksi Tersedia</div>
                </div>
            </td>
            <td width="20%">
                <div class="stats-card">
                    <div class="stats-value" style="color: #d97706;">{{ number_format($summary['total_borrowed']) }}</div>
                    <div class="stats-label">Sedang Dipinjam</div>
                </div>
            </td>
            <td width="20%">
                <div class="stats-card">
                    <div class="stats-value" style="color: #dc2626;">{{ number_format($summary['total_quarantine']) }}</div>
                    <div class="stats-label">Karantina / Perbaikan</div>
                </div>
            </td>
        </tr>
    </table>

    <!-- BREAKDOWN KONDISI FISIK / STATUS -->
    <div class="section-title">Breakdown Status Ketersediaan Eksemplar</div>
    <div style="margin-bottom: 15px;">
        @forelse($availability_status_breakdown as $status => $count)
            <span class="badge badge-info" style="font-size: 10px; margin-right: 8px;">
                {{ $status }}: {{ $count }} eksemplar
            </span>
        @empty
            <span style="color: #64748b;">Belum ada data status</span>
        @endforelse
    </div>

    <!-- DAFTAR EKSEMPLAR KOLEKSI -->
    <div class="section-title">Daftar Eksemplar Koleksi Perpustakaan</div>
    <table class="table-data">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th>No Barcode / Barcode</th>
                <th>Judul Katalog</th>
                <th>Pengarang</th>
                <th>No Panggil</th>
                <th>Lokasi Ruangan</th>
                <th>Status Ketersediaan</th>
                <th>Karantina</th>
            </tr>
        </thead>
        <tbody>
            @forelse($collections_list as $index => $item)
                <tr>
                    <td align="center">{{ $index + 1 }}</td>
                    <td><strong>{{ $item->barcode_no }}</strong></td>
                    <td>{{ $item->catalog->title ?? '-' }}</td>
                    <td>{{ $item->catalog->author_main ?? '-' }}</td>
                    <td>{{ $item->exemplar_call_number ?? '-' }}</td>
                    <td>{{ $item->room_location_code ?? '-' }}</td>
                    <td>
                        @if($item->availability_status === 'AVAILABLE') <span class="badge badge-success">TERSEDIA</span>
                        @elseif($item->availability_status === 'BORROWED') <span class="badge badge-warning">DIPINJAM</span>
                        @else <span class="badge badge-danger">{{ $item->availability_status }}</span>
                        @endif
                    </td>
                    <td align="center">
                        @if($item->is_quarantine)
                            <span class="badge badge-danger">YA</span>
                        @else
                            <span class="badge badge-success">TIDAK</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" align="center">Tidak ada data koleksi</td></tr>
            @endforelse
        </tbody>
    </table>

@endsection
