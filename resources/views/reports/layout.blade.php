<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Laporan Perpustakaan' }} - Dinas Kearsipan dan Perpustakaan Kabupaten Buleleng</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm 15mm 15mm 15mm;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1f2937;
            background: #fff;
            margin: 0;
            padding: 0;
            font-size: 11px;
            line-height: 1.4;
        }

        .kop-container {
            width: 100%;
            border-bottom: 3px double #1e3a8a;
            padding-bottom: 8px;
            margin-bottom: 15px;
        }

        .kop-table {
            width: 100%;
            border-collapse: collapse;
        }

        .kop-table td {
            border: none;
            padding: 0;
            vertical-align: middle;
        }

        .kop-title-main {
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
            color: #111827;
        }

        .kop-title-sub {
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
            color: #1e3a8a;
            margin: 2px 0;
            letter-spacing: 0.5px;
        }

        .kop-address {
            font-size: 9.5px;
            color: #4b5563;
            margin: 0;
        }

        .report-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .report-title {
            font-size: 15px;
            font-weight: bold;
            text-transform: uppercase;
            color: #111827;
            margin: 0 0 4px 0;
            text-decoration: underline;
        }

        .report-subtitle {
            font-size: 10.5px;
            color: #4b5563;
            margin: 0;
        }

        .stats-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .stats-card {
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            padding: 8px 12px;
            text-align: center;
            border-radius: 4px;
        }

        .stats-value {
            font-size: 16px;
            font-weight: bold;
            color: #1e3a8a;
        }

        .stats-label {
            font-size: 9px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
        }

        .table-data {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .table-data th, .table-data td {
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
            text-align: left;
            font-size: 10px;
        }

        .table-data th {
            background-color: #f1f5f9;
            color: #1e293b;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 9.5px;
        }

        .table-data tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .section-title {
            font-size: 12px;
            font-weight: bold;
            color: #1e3a8a;
            margin: 15px 0 8px 0;
            border-left: 3px solid #1e3a8a;
            padding-left: 6px;
        }

        .signature-container {
            width: 100%;
            margin-top: 30px;
            page-break-inside: avoid;
        }

        .signature-table {
            width: 100%;
            border-collapse: collapse;
        }

        .signature-table td {
            border: none;
            vertical-align: top;
            font-size: 10.5px;
        }

        .badge {
            display: inline-block;
            padding: 2px 6px;
            font-size: 8.5px;
            font-weight: bold;
            border-radius: 3px;
            text-transform: uppercase;
        }

        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef9c3; color: #854d0e; }
        .badge-danger  { background: #fee2e2; color: #991b1b; }
        .badge-info    { background: #e0f2fe; color: #075985; }

        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
        }
    </style>
</head>
<body>

    <!-- KOP SURAT PERPUSTAKAAN KABUPATEN BULELENG -->
    <div class="kop-container">
        <table class="kop-table">
            <tr>
                <td width="15%" align="center">
                    <div style="font-size: 32px; line-height: 1;">🏛️</div>
                </td>
                <td width="70%" align="center">
                    <div class="kop-title-main">Pemerintah Kabupaten Buleleng</div>
                    <div class="kop-title-sub">Dinas Kearsipan dan Perpustakaan Daerah</div>
                    <div class="kop-address">Jl. Pahlawan No. 1 Singaraja, Bali 81117 | Telp/Fax: (0362) 22536</div>
                    <div class="kop-address">Website: perpustakaan.bulelengkab.go.id | Email: disarsipus@bulelengkab.go.id</div>
                </td>
                <td width="15%" align="center">
                    <div style="font-size: 32px; line-height: 1;">📚</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- JUDUL LAPORAN & PERIODE -->
    <div class="report-header">
        <div class="report-title">{{ $title ?? 'LAPORAN SISTEM PERPUSTAKAAN' }}</div>
        <div class="report-subtitle">
            Periode Laporan: <strong>{{ $period['start_date'] ?? 'Semua Waktu' }}</strong> s/d <strong>{{ $period['end_date'] ?? 'Semua Waktu' }}</strong>
        </div>
    </div>

    <!-- CONTENT SLOT -->
    @yield('content')

    <!-- BLOK TANDA TANGAN KEPALA PERPUSTAKAAN / PETUGAS -->
    <div class="signature-container">
        <table class="signature-table">
            <tr>
                <td width="55%"></td>
                <td width="45%" align="center">
                    <p style="margin: 0 0 4px 0;">Singaraja, {{ \Carbon\Carbon::now()->translatedFormat('d F Y') }}</p>
                    <p style="margin: 0 0 50px 0; font-weight: bold;">
                        Kepala Dinas Kearsipan dan Perpustakaan<br>Kabupaten Buleleng
                    </p>
                    <p style="margin: 0; font-weight: bold; text-decoration: underline;">
                        ( Drs. I Karang Gede, M.Si )
                    </p>
                    <p style="margin: 2px 0 0 0; font-size: 9.5px; color: #4b5563;">
                        NIP. 19710815 199603 1 004
                    </p>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
