<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Aktivasi Akun Digital Anggota ATLAS</title>
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
      padding: 28px 32px;
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
    .content {
      padding: 32px;
    }
    .greeting {
      font-size: 16px;
      font-weight: 700;
      color: #0f172a;
      margin-bottom: 12px;
    }
    .info-card {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 16px 20px;
      margin: 20px 0;
    }
    .info-row {
      display: flex;
      justify-content: space-between;
      margin-bottom: 8px;
      font-size: 13px;
    }
    .info-row:last-child {
      margin-bottom: 0;
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
    .cta-container {
      text-align: center;
      margin: 32px 0;
    }
    .btn {
      display: inline-block;
      background-color: #0B2256;
      color: #ffffff !important;
      text-decoration: none;
      padding: 14px 32px;
      border-radius: 10px;
      font-size: 14px;
      font-weight: 700;
      letter-spacing: 0.3px;
    }
    .notice {
      background: #eff6ff;
      border-left: 4px solid #2563eb;
      padding: 12px 16px;
      border-radius: 6px;
      font-size: 12px;
      color: #1e40af;
      margin: 20px 0;
    }
    .alt-link {
      font-size: 11px;
      color: #64748b;
      word-break: break-all;
      margin-top: 24px;
      padding-top: 16px;
      border-top: 1px solid #f1f5f9;
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
      <div class="greeting">Halo, {{ $member->name }}</div>
      <p style="font-size: 14px; margin: 0 0 16px;">
        Pendaftaran keanggotaan Anda di Perpustakaan Daerah Kabupaten Buleleng telah berhasil didaftarkan melalui loket pelayanan perpustakaan.
      </p>

      <div class="info-card">
        <div class="info-row">
          <span class="info-label">Nama Anggota:</span>
          <span class="info-value">{{ $member->name }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Nomor Anggota:</span>
          <span class="info-value" style="font-family: monospace; color: #1d4ed8;">{{ $member->member_no }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Alamat Email:</span>
          <span class="info-value">{{ $member->email }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Status Keanggotaan:</span>
          <span class="info-value" style="color: #059669;">Aktif di Loket</span>
        </div>
      </div>

      <p style="font-size: 13px; color: #334155;">
        Untuk mengakses akun digital Anda di portal Sistem ATLAS (Layanan Mandiri, reservasi koleksi buku, dan cek riwayat peminjaman), silakan lakukan verifikasi email dan pembuatan kata sandi akun Anda melalui tombol di bawah ini:
      </p>

      <div class="cta-container">
        <a href="{{ $activationUrl }}" class="btn" target="_blank">Aktivasi Akun &amp; Buat Password</a>
      </div>

      <div class="notice">
        <strong>Ketentuan Aktivasi:</strong>
        <ul style="margin: 6px 0 0; padding-left: 18px;">
          <li>Tautan aktivasi ini berlaku selama <strong>{{ $expiresHours }} jam</strong> sejak email dikirimkan.</li>
          <li>Tautan aktivasi hanya dapat digunakan 1 (satu) kali.</li>
          <li>Petugas perpustakaan tidak pernah meminta atau mengetahui kata sandi yang Anda buat.</li>
        </ul>
      </div>

      <p style="font-size: 12px; color: #64748b;">
        *Catatan: Meskipun belum mengaktifkan akun digital, Anda sudah dapat meminjam buku fisik secara langsung di loket sirkulasi perpustakaan dengan menunjukkan kartu anggota Anda.
      </p>

      <div class="alt-link">
        Bila tombol di atas tidak dapat diklik, salin dan tempel tautan berikut ke peramban (browser) Anda:<br>
        <a href="{{ $activationUrl }}" style="color: #2563eb;">{{ $activationUrl }}</a>
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
