<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Informasi Tenggat Peminjaman Buku</title>
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
    .content {
      padding: 32px;
    }
    .badge-status {
      display: inline-block;
      font-size: 11px;
      font-weight: 700;
      padding: 4px 12px;
      border-radius: 6px;
      margin-bottom: 12px;
      text-transform: uppercase;
    }
    .badge-new {
      background-color: #eff6ff;
      color: #1e40af;
      border: 1px solid #bfdbfe;
    }
    .badge-renewal {
      background-color: #f0fdf4;
      color: #166534;
      border: 1px solid #bbf7d0;
    }
    .badge-reminder {
      background-color: #fffbeb;
      color: #b45309;
      border: 1px solid #fde68a;
    }
    .badge-overdue {
      background-color: #fef2f2;
      color: #b91c1c;
      border: 1px solid #fecaca;
    }
    .greeting {
      font-size: 16px;
      font-weight: 700;
      color: #0f172a;
      margin-bottom: 8px;
    }
    .due-box {
      border-radius: 12px;
      padding: 16px 20px;
      text-align: center;
      margin: 20px 0;
    }
    .due-box-normal {
      background: #eff6ff;
      border: 1px solid #93c5fd;
    }
    .due-box-alert {
      background: #fef2f2;
      border: 1px solid #fca5a5;
    }
    .due-label {
      font-size: 11px;
      text-transform: uppercase;
      font-weight: 700;
      letter-spacing: 0.5px;
      margin-bottom: 4px;
    }
    .due-label-normal {
      color: #1e40af;
    }
    .due-label-alert {
      color: #991b1b;
    }
    .due-date {
      font-size: 22px;
      font-weight: 800;
      letter-spacing: 0.5px;
    }
    .due-date-normal {
      color: #0B2256;
    }
    .due-date-alert {
      color: #b91c1c;
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
    .policy-box {
      background: #f8fafc;
      border-left: 4px solid #0B2256;
      padding: 12px 16px;
      border-radius: 6px;
      font-size: 12px;
      color: #334155;
      margin: 20px 0;
      line-height: 1.6;
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
      @if($notificationType === \App\Mail\LoanDueDateNotificationMail::TYPE_RENEWAL)
        <div class="badge-status badge-renewal">Status: Masa Pinjam Diperpanjang</div>
      @elseif($notificationType === \App\Mail\LoanDueDateNotificationMail::TYPE_REMINDER_SOON)
        <div class="badge-status badge-reminder">Status: Pengingat Tenggat Pengembalian</div>
      @elseif($notificationType === \App\Mail\LoanDueDateNotificationMail::TYPE_OVERDUE)
        <div class="badge-status badge-overdue">Status: Melewati Batas Waktu Tenggat</div>
      @else
        <div class="badge-status badge-new">Status: Peminjaman Tercatat</div>
      @endif

      <div class="greeting">Halo, {{ $loan->member?->name }}</div>

      @if($notificationType === \App\Mail\LoanDueDateNotificationMail::TYPE_RENEWAL)
        <p style="font-size: 14px; margin: 0 0 16px; color: #334155;">
          Perpanjangan masa peminjaman koleksi buku perpustakaan Anda telah berhasil dicatat. Berikut adalah rincian masa pinjam dan tanggal jatuh tempo terbaru Anda:
        </p>
      @elseif($notificationType === \App\Mail\LoanDueDateNotificationMail::TYPE_REMINDER_SOON)
        <p style="font-size: 14px; margin: 0 0 16px; color: #334155;">
          Ini adalah pengingat resmi bahwa masa peminjaman buku perpustakaan Anda akan segera berakhir. Mohon memperhatikan tanggal jatuh tempo di bawah ini:
        </p>
      @elseif($notificationType === \App\Mail\LoanDueDateNotificationMail::TYPE_OVERDUE)
        <p style="font-size: 14px; margin: 0 0 16px; color: #334155;">
          Masa peminjaman buku perpustakaan Anda telah melewati batas waktu tenggat pengembalian. Kami mengimbau Anda untuk segera mengembalikan buku fisik ke Loket Sirkulasi:
        </p>
      @else
        <p style="font-size: 14px; margin: 0 0 16px; color: #334155;">
          Peminjaman buku fisik perpustakaan telah berhasil dicatat atas nama Anda di Loket Layanan Sirkulasi Perpustakaan Daerah Kabupaten Buleleng. Berikut adalah rincian informasi peminjaman dan tenggat pengembalian:
        </p>
      @endif

      {{-- Highlight Tenggat Pengembalian (Due Date) --}}
      <div class="due-box {{ $notificationType === \App\Mail\LoanDueDateNotificationMail::TYPE_OVERDUE ? 'due-box-alert' : 'due-box-normal' }}">
        <div class="due-label {{ $notificationType === \App\Mail\LoanDueDateNotificationMail::TYPE_OVERDUE ? 'due-label-alert' : 'due-label-normal' }}">
          Tenggat Pengembalian Buku (Jatuh Tempo):
        </div>
        <div class="due-date {{ $notificationType === \App\Mail\LoanDueDateNotificationMail::TYPE_OVERDUE ? 'due-date-alert' : 'due-date-normal' }}">
          {{ $loan->due_date ? \Carbon\Carbon::parse($loan->due_date)->translatedFormat('l, d F Y') : '-' }}
        </div>
      </div>

      <div class="info-card">
        <div class="info-row">
          <span class="info-label">Nomor Transaksi Pinjam:</span>
          <span class="info-value" style="font-family: monospace; color: #1e40af;">{{ $loan->loan_number }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Judul Buku:</span>
          <span class="info-value">{{ $loan->collection?->catalog?->title ?? 'Judul Buku' }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Pengarang:</span>
          <span class="info-value">{{ $loan->collection?->catalog?->author ?? '-' }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Nomor Barcode Eksemplar:</span>
          <span class="info-value" style="font-family: monospace;">{{ $loan->collection?->barcode_no ?? '-' }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Nama Peminjam:</span>
          <span class="info-value">{{ $loan->member?->name }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Nomor Anggota:</span>
          <span class="info-value" style="font-family: monospace;">{{ $loan->member?->member_no }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Tanggal Peminjaman:</span>
          <span class="info-value">
            {{ $loan->borrow_date ? \Carbon\Carbon::parse($loan->borrow_date)->translatedFormat('d F Y') : ($loan->loan_date ? \Carbon\Carbon::parse($loan->loan_date)->translatedFormat('d F Y') : '-') }}
          </span>
        </div>
        <div class="info-row">
          <span class="info-label">Jumlah Perpanjangan:</span>
          <span class="info-value">{{ (int) $loan->extension_count }} kali (maks. 2 kali)</span>
        </div>
      </div>

      <div class="policy-box">
        <strong>Ketentuan Pengembalian Buku:</strong>
        <p style="margin: 4px 0 0;">
          Dinas Arsip dan Perpustakaan Daerah Kabupaten Buleleng tidak memberlakukan denda uang keterlambatan. Pengembalian buku secara tepat waktu adalah bentuk kepedulian bersama agar seluruh masyarakat Buleleng dapat menikmati akses koleksi pustaka secara merata.
        </p>
        <p style="margin: 6px 0 0;">
          Pengembalian buku dapat dilakukan langsung di <strong>Loket Pelayanan Sirkulasi</strong> Perpustakaan Daerah Kabupaten Buleleng pada jam operasional kerja.
        </p>
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
