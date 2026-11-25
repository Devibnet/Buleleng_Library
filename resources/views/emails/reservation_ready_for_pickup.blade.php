<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Buku Reservasi Siap Diambil di Loket</title>
  <style>
    body {
      margin: 0;
      padding: 0;
      background-color: #f1f5f9;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
      color: #1e293b;
      line-height: 1.6;
    }
    .wrapper {
      max-width: 600px;
      margin: 30px auto;
      background: #ffffff;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
      border: 1px solid #e2e8f0;
    }
    .header {
      background-color: #0B2256;
      padding: 26px 32px;
      text-align: center;
      color: #ffffff;
    }
    .header h1 {
      margin: 0;
      font-size: 18px;
      font-weight: 800;
      letter-spacing: 0.5px;
      text-transform: uppercase;
    }
    .header p {
      margin: 4px 0 0;
      font-size: 12px;
      color: #93c5fd;
    }
    .badge-ready {
      display: inline-block;
      background-color: #dcfce7;
      color: #166534;
      font-size: 11px;
      font-weight: 700;
      padding: 4px 12px;
      border-radius: 6px;
      margin-bottom: 12px;
      border: 1px solid #bbf7d0;
    }
    .content {
      padding: 32px;
    }
    .greeting {
      font-size: 16px;
      font-weight: 700;
      color: #0f172a;
      margin-bottom: 8px;
    }
    .info-card {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 18px 20px;
      margin: 20px 0;
    }
    .info-row {
      display: flex;
      justify-content: space-between;
      margin-bottom: 10px;
      font-size: 13px;
      border-bottom: 1px dashed #e2e8f0;
      padding-bottom: 8px;
    }
    .info-row:last-child {
      margin-bottom: 0;
      border-bottom: none;
      padding-bottom: 0;
    }
    .info-label {
      color: #64748b;
      font-weight: 600;
    }
    .info-value {
      color: #0f172a;
      font-weight: 700;
      text-align: right;
    }
    .code-box {
      background: #eff6ff;
      border: 1px solid #bfdbfe;
      border-radius: 10px;
      padding: 14px;
      text-align: center;
      margin: 20px 0;
    }
    .code-label {
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: #1e40af;
      font-weight: 700;
      margin-bottom: 4px;
    }
    .code-value {
      font-family: monospace;
      font-size: 20px;
      font-weight: 800;
      color: #0B2256;
      letter-spacing: 1px;
    }
    .deadline-notice {
      background: #fffbeb;
      border-left: 4px solid #f59e0b;
      padding: 12px 16px;
      border-radius: 6px;
      font-size: 12px;
      color: #92400e;
      margin: 20px 0;
    }
    .instructions {
      font-size: 12px;
      color: #334155;
      background: #f8fafc;
      border-radius: 8px;
      padding: 14px 18px;
      margin: 18px 0;
    }
    .footer {
      background-color: #f8fafc;
      padding: 20px 32px;
      text-align: center;
      border-top: 1px solid #e2e8f0;
      font-size: 11px;
      color: #64748b;
    }
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="header">
      <h1>Sistem Perpustakaan ATLAS</h1>
      <p>Dinas Arsip dan Perpustakaan Daerah Kabupaten Buleleng</p>
    </div>

    <div class="content">
      <div class="badge-ready">STATUS: SIAP DIAMBIL DI LOKET</div>
      <div class="greeting">Halo, {{ $loan->member?->name }}</div>
      <p style="font-size: 14px; margin: 0 0 16px; color: #334155;">
        Koleksi buku yang Anda pesan melalui reservasi online telah berhasil diverifikasi dan disiapkan oleh petugas kami di Loket Layanan Sirkulasi.
      </p>

      <div class="code-box">
        <div class="code-label">Tunjukkan Kode Reservasi Ini di Loket:</div>
        <div class="code-value">{{ $loan->reservation_code }}</div>
      </div>

      <div class="info-card">
        <div class="info-row">
          <span class="info-label">Judul Buku:</span>
          <span class="info-value">{{ $loan->collection?->catalog?->title ?? 'Judul Buku' }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Pengarang:</span>
          <span class="info-value">{{ $loan->collection?->catalog?->author ?? '-' }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Nomor Eksemplar (Barcode):</span>
          <span class="info-value" style="font-family: monospace;">{{ $loan->collection?->barcode_no ?? '-' }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Nama Pemustaka:</span>
          <span class="info-value">{{ $loan->member?->name }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Nomor Anggota:</span>
          <span class="info-value" style="font-family: monospace;">{{ $loan->member?->member_no }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Batas Waktu Pengambilan:</span>
          <span class="info-value" style="color: #b91c1c;">
            {{ $loan->pickup_deadline ? $loan->pickup_deadline->translatedFormat('l, d F Y - H:i') . ' WITA' : '3 Hari Kerja' }}
          </span>
        </div>
        <div class="info-row">
          <span class="info-label">Lokasi Pengambilan:</span>
          <span class="info-value">Loket Layanan Sirkulasi DAPD</span>
        </div>
      </div>

      <div class="deadline-notice">
        <strong>Pemberitahuan Batas Waktu Pengambilan:</strong>
        <p style="margin: 4px 0 0;">
          Harap mengambil buku fisik sebelum <strong>{{ $loan->pickup_deadline ? $loan->pickup_deadline->translatedFormat('d F Y') : 'batas tenggat' }}</strong>. Apabila melewati batas waktu tersebut, sistem akan membatalkan reservasi secara otomatis agar buku dapat dipinjam oleh pemustaka lain.
        </p>
      </div>

      <div class="instructions">
        <strong>Petunjuk Pengambilan:</strong>
        <ol style="margin: 6px 0 0; padding-left: 18px; line-height: 1.7;">
          <li>Kunjungi Loket Sirkulasi di Gedung Perpustakaan Daerah Kabupaten Buleleng.</li>
          <li>Sampaikan Kode Reservasi <strong>{{ $loan->reservation_code }}</strong> atau tunjukkan Kartu Tanda Anggota (KTA) fisik/digital kepada petugas.</li>
          <li>Petugas akan menyerahkan buku dan mencatat tanggal jatuh tempo pengembalian buku.</li>
        </ol>
      </div>
    </div>

    <div class="footer">
      <strong>Dinas Arsip dan Perpustakaan Daerah Kabupaten Buleleng</strong><br>
      Jl. Wrekudara No.1, Singaraja, Bali 81119<br>
      Website: https://bulelengkab.go.id • Email: perpustakaan@bulelengkab.go.id<br>
      <em>Pesan ini dikirimkan otomatis oleh Sistem ATLAS. Mohon tidak membalas email ini.</em>
    </div>
  </div>
</body>
</html>
