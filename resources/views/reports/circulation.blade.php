@extends('reports.layout')

@section('content')

    <!-- RINGKASAN AGREGASI SIRKULASI -->
    <table class="stats-grid">
        <tr>
            <td width="20%">
                <div class="stats-card">
                    <div class="stats-value">{{ number_format($summary['total_loans']) }}</div>
                    <div class="stats-label">Total Transaksi</div>
                </div>
            </td>
            <td width="20%">
                <div class="stats-card">
                    <div class="stats-value" style="color: #0284c7;">{{ number_format($summary['total_borrowed']) }}</div>
                    <div class="stats-label">Sedang Dipinjam</div>
                </div>
            </td>
            <td width="20%">
                <div class="stats-card">
                    <div class="stats-value" style="color: #16a34a;">{{ number_format($summary['total_returned']) }}</div>
                    <div class="stats-label">Dikembalikan</div>
                </div>
            </td>
            <td width="20%">
                <div class="stats-card">
                    <div class="stats-value" style="color: #dc2626;">{{ number_format($summary['total_overdue']) }}</div>
                    <div class="stats-label">Terlambat</div>
                </div>
            </td>
            <td width="20%">
                <div class="stats-card">
                    <div class="stats-value" style="color: #d97706;">Rp {{ number_format($summary['total_fine_amount'], 0, ',', '.') }}</div>
                    <div class="stats-label">Total Denda</div>
                </div>
            </td>
        </tr>
    </table>

    <!-- TOP BUKU & TOP ANGGOTA -->
    <table width="100%" style="border-collapse: collapse; margin-bottom: 15px;">
        <tr>
            <td width="50%" style="vertical-align: top; padding-right: 6px;">
                <div class="section-title">Top 10 Buku Paling Sering Dipinjam</div>
                <table class="table-data">
                    <thead>
                        <tr>
                            <th width="8%">No</th>
                            <th>Judul Buku / Pengarang</th>
                            <th width="20%" align="center">Dipinjam</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($top_books as $index => $book)
                            <tr>
                                <td align="center">{{ $index + 1 }}</td>
                                <td>
                                    <strong>{{ $book->title }}</strong><br>
                                    <small style="color: #64748b;">{{ $book->author_main ?? 'N/A' }}</small>
                                </td>
                                <td align="center"><strong>{{ $book->total_borrowed }}x</strong></td>
                            </tr>
                        @empty
                            <tr><td colspan="3" align="center">Tidak ada data</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
            <td width="50%" style="vertical-align: top; padding-left: 6px;">
                <div class="section-title">Top 10 Anggota Paling Aktif Meminjam</div>
                <table class="table-data">
                    <thead>
                        <tr>
                            <th width="8%">No</th>
                            <th>No. Member / Nama</th>
                            <th width="20%" align="center">Pinjaman</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($top_members as $index => $member)
                            <tr>
                                <td align="center">{{ $index + 1 }}</td>
                                <td>
                                    <strong>{{ $member->name }}</strong><br>
                                    <small style="color: #64748b;">{{ $member->member_no }} ({{ $member->job_category ?? '-' }})</small>
                                </td>
                                <td align="center"><strong>{{ $member->total_loans }}x</strong></td>
                            </tr>
                        @empty
                            <tr><td colspan="3" align="center">Tidak ada data</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <!-- KINERJA OPERATOR -->
    <div class="section-title">Laporan Kinerja Operator (Staf Staf Loket)</div>
    <table class="table-data">
        <thead>
            <tr>
                <th width="8%">No</th>
                <th>Username / Nama Staf</th>
                <th>Role</th>
                <th width="25%" align="center">Proses Peminjaman</th>
                <th width="25%" align="center">Proses Pengembalian</th>
            </tr>
        </thead>
        <tbody>
            @forelse($operator_performance as $index => $op)
                <tr>
                    <td align="center">{{ $index + 1 }}</td>
                    <td><strong>{{ $op->name }}</strong> ({{ $op->username }})</td>
                    <td><span class="badge badge-info">{{ $op->role }}</span></td>
                    <td align="center"><strong>{{ $op->processed_borrows }}</strong> transaksi</td>
                    <td align="center"><strong>{{ $op->processed_returns }}</strong> transaksi</td>
                </tr>
            @empty
                <tr><td colspan="5" align="center">Tidak ada data staf</td></tr>
            @endforelse
        </tbody>
    </table>

    <!-- DAFTAR TRANSAKSI PEMINJAMAN -->
    <div class="section-title">Detail Transaksi Sirkulasi</div>
    <table class="table-data">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th>No Transaksi</th>
                <th>Anggota</th>
                <th>Judul Buku</th>
                <th>Tgl Pinjam</th>
                <th>Tgl Jatuh Tempo</th>
                <th>Tgl Kembali</th>
                <th>Status</th>
                <th>Denda</th>
            </tr>
        </thead>
        <tbody>
            @forelse($loans_list as $index => $loan)
                <tr>
                    <td align="center">{{ $index + 1 }}</td>
                    <td><strong>{{ $loan->loan_number }}</strong></td>
                    <td>{{ $loan->member->name ?? '-' }}</td>
                    <td>{{ $loan->collection->catalog->title ?? '-' }}</td>
                    <td>{{ $loan->borrow_date ? $loan->borrow_date->format('d/m/Y') : '-' }}</td>
                    <td>{{ $loan->due_date ? $loan->due_date->format('d/m/Y') : '-' }}</td>
                    <td>{{ $loan->return_date ? $loan->return_date->format('d/m/Y') : '-' }}</td>
                    <td>
                        @if($loan->status === 'BORROWED') <span class="badge badge-info">DIPINJAM</span>
                        @elseif($loan->status === 'RETURNED') <span class="badge badge-success">KEMBALI</span>
                        @elseif($loan->status === 'OVERDUE') <span class="badge badge-danger">OVERDUE</span>
                        @else <span class="badge badge-warning">{{ $loan->status }}</span>
                        @endif
                    </td>
                    <td>Rp {{ number_format($loan->fine_amount, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="9" align="center">Tidak ada rincian data transaksi sirkulasi</td></tr>
            @endforelse
        </tbody>
    </table>

@endsection
