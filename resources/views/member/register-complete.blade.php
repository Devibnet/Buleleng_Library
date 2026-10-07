<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lengkapi Registrasi Anggota Perpustakaan</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --primary-light: #eff6ff;
            --primary-dark: #1e40af;
            --secondary: #0f172a;
            --bg-gradient: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            --card-bg: rgba(255, 255, 255, 0.98);
            --border-color: #e2e8f0;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --danger: #ef4444;
            --success: #10b981;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 24px;
            --shadow-subtle: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            --shadow-card: 0 20px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg-gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2.5rem 1rem;
            color: var(--text-main);
        }

        .container {
            width: 100%;
            max-width: 780px;
            margin: 0 auto;
        }

        .register-card {
            background: var(--card-bg);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-card);
            border: 1px solid rgba(255, 255, 255, 0.15);
            overflow: hidden;
            backdrop-filter: blur(12px);
        }

        .header-bar {
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
            color: #ffffff;
            padding: 2.25rem 2.5rem;
            position: relative;
        }

        .header-bar::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #38bdf8, #818cf8, #c084fc);
        }

        .header-bar .badge-step {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(8px);
            padding: 0.35rem 0.85rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .header-bar h1 {
            font-size: 1.65rem;
            font-weight: 800;
            letter-spacing: -0.025em;
            line-height: 1.25;
            margin-bottom: 0.5rem;
        }

        .header-bar p {
            font-size: 0.925rem;
            color: #bfdbfe;
            line-height: 1.5;
        }

        .card-body {
            padding: 2.25rem 2.5rem;
        }

        /* Google Connected Profile Box */
        .google-account-banner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.25rem;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: var(--radius-md);
            padding: 1rem 1.25rem;
            margin-bottom: 2rem;
        }

        .google-profile-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .avatar-wrapper {
            position: relative;
            width: 52px;
            height: 52px;
            flex-shrink: 0;
        }

        .avatar-img {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #3b82f6;
            background: #e2e8f0;
        }

        .google-icon-badge {
            position: absolute;
            bottom: -2px;
            right: -2px;
            width: 20px;
            height: 20px;
            background: #ffffff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
        }

        .google-icon-badge svg {
            width: 13px;
            height: 13px;
        }

        .google-text-info .profile-title {
            font-size: 0.785rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .google-text-info .profile-email {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-main);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #dcfce7;
            color: #15803d;
            padding: 0.35rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
            border: 1px solid #bbf7d0;
            white-space: nowrap;
        }

        /* Form Sections */
        .form-section {
            margin-bottom: 2rem;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 1rem;
            font-weight: 700;
            color: var(--secondary);
            margin-bottom: 1.25rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #f1f5f9;
        }

        .section-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 26px;
            height: 26px;
            background: #dbeafe;
            color: var(--primary);
            border-radius: 50%;
            font-size: 0.8rem;
            font-weight: 800;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            font-size: 0.865rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.45rem;
        }

        label .required {
            color: var(--danger);
            margin-left: 2px;
        }

        .input-hint {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 0.35rem;
        }

        .input-wrapper {
            position: relative;
        }

        input[type="text"],
        input[type="email"],
        input[type="date"],
        select,
        textarea {
            width: 100%;
            padding: 0.725rem 0.95rem;
            font-family: inherit;
            font-size: 0.925rem;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-md);
            background: #ffffff;
            color: var(--text-main);
            transition: all 0.2s ease-in-out;
            outline: none;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="date"]:focus,
        select:focus,
        textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3.5px rgba(37, 99, 235, 0.15);
        }

        input[readonly] {
            background-color: #f1f5f9;
            color: #475569;
            cursor: not-allowed;
            border-color: #cbd5e1;
        }

        textarea {
            resize: vertical;
            min-height: 84px;
            line-height: 1.5;
        }

        select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%2364748b' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.75rem center;
            background-repeat: no-repeat;
            background-size: 1.4em 1.4em;
            padding-right: 2.5rem;
        }

        /* Error States */
        .is-invalid {
            border-color: var(--danger) !important;
            background-color: #fffbfa !important;
        }

        .is-invalid:focus {
            box-shadow: 0 0 0 3.5px rgba(239, 68, 68, 0.15) !important;
        }

        .error-message {
            color: var(--danger);
            font-size: 0.775rem;
            font-weight: 500;
            margin-top: 0.35rem;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .alert-danger {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 1rem 1.25rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.75rem;
            font-size: 0.885rem;
            line-height: 1.5;
        }

        .alert-danger ul {
            margin-left: 1.25rem;
            margin-top: 0.35rem;
        }

        /* Address copy toggle */
        .same-address-check {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.4rem;
            font-size: 0.825rem;
            color: #475569;
            cursor: pointer;
            user-select: none;
        }

        .same-address-check input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--primary);
            cursor: pointer;
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 1rem;
            padding-top: 1.5rem;
            border-top: 1px solid #f1f5f9;
            margin-top: 2rem;
        }

        .btn-submit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff;
            font-family: inherit;
            font-size: 0.95rem;
            font-weight: 700;
            padding: 0.85rem 1.85rem;
            border-radius: var(--radius-md);
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35);
            transition: all 0.2s ease-in-out;
        }

        .btn-submit:hover {
            background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.45);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .btn-cancel {
            display: inline-flex;
            align-items: center;
            color: var(--text-muted);
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            padding: 0.75rem 1rem;
            border-radius: var(--radius-md);
            transition: all 0.15s ease;
        }

        .btn-cancel:hover {
            color: var(--text-main);
            background: #f1f5f9;
        }

        /* Responsive */
        @media (max-width: 640px) {
            body {
                padding: 1rem 0.5rem;
            }

            .header-bar {
                padding: 1.5rem;
            }

            .card-body {
                padding: 1.5rem;
            }

            .grid-2 {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .google-account-banner {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.85rem;
            }

            .form-actions {
                flex-direction: column-reverse;
            }

            .btn-submit, .btn-cancel {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="register-card">
        <!-- Header -->
        <div class="header-bar">
            <div class="badge-step">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <polyline points="16 11 18 13 22 9"></polyline>
                </svg>
                Langkah 2 dari 2 &bull; Pendaftaran Anggota
            </div>
            <h1>Lengkapi Data Diri Anggota</h1>
            <p>Satu langkah lagi untuk mengaktifkan keanggotaan perpustakaan Anda dan menikmati fasilitas peminjaman buku.</p>
        </div>

        <div class="card-body">
            @if ($errors->any())
                <div class="alert-danger">
                    <strong>Mohon periksa kembali formulir Anda:</strong>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Google Profile Connected Banner -->
            <div class="google-account-banner">
                <div class="google-profile-info">
                    <div class="avatar-wrapper">
                        <img 
                            src="{{ $googleUser['photo_path'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($googleUser['name'] ?? 'Member') . '&background=2563eb&color=fff' }}" 
                            alt="Foto Profil Google" 
                            class="avatar-img"
                            referrerpolicy="no-referrer"
                        >
                        <span class="google-icon-badge" title="Terkoneksi dengan Google">
                            <svg viewBox="0 0 24 24">
                                <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.8-2.4 3.66v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.15z"/>
                                <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.25v3.15C3.26 21.36 7.33 24 12 24z"/>
                                <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.25C.45 8.18 0 9.98 0 12s.45 3.82 1.25 5.42l4.03-3.15z"/>
                                <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.33 0 3.26 2.64 1.25 6.58l4.03 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
                            </svg>
                        </span>
                    </div>
                    <div class="google-text-info">
                        <div class="profile-title">Akun Google Terhubung</div>
                        <div class="profile-email">{{ $googleUser['email'] ?? '-' }}</div>
                    </div>
                </div>
                <div class="status-badge">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    Terverifikasi
                </div>
            </div>

            <!-- Form -->
            <form action="{{ route('member.register.complete.post') }}" method="POST" id="memberRegisterForm" enctype="multipart/form-data">
                @csrf

                <!-- Section 1: Identitas Pribadi -->
                <div class="form-section">
                    <div class="section-title">
                        <span class="section-number">1</span>
                        Identitas Pribadi
                    </div>

                    <div class="grid-2">
                        <!-- Email (Readonly dari Google) -->
                        <div class="form-group">
                            <label for="email">Alamat Email <span class="required">*</span></label>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                value="{{ $googleUser['email'] ?? '' }}" 
                                readonly 
                                title="Email diperoleh dari akun Google Anda"
                            >
                            <span class="input-hint">Email tersinkronisasi otomatis dari akun Google Anda.</span>
                        </div>

                        <!-- Nama Lengkap (Editable, default dari Google) -->
                        <div class="form-group">
                            <label for="name">Nama Lengkap <span class="required">*</span></label>
                            <input 
                                type="text" 
                                id="name" 
                                name="name" 
                                value="{{ old('name', $googleUser['name'] ?? '') }}" 
                                placeholder="Masukkan nama lengkap sesuai KTP"
                                class="@error('name') is-invalid @enderror"
                                required
                            >
                            @error('name')
                                <div class="error-message">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12" stroke="#fff" stroke-width="2"/><line x1="12" y1="16" x2="12.01" y2="16" stroke="#fff" stroke-width="2"/></svg>
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <!-- Foto Kartu Anggota -->
                        <div class="form-group full-width">
                            <label for="photo">Pas Foto (Untuk Kartu Anggota) <span class="required">*</span></label>
                            <input 
                                type="file" 
                                id="photo" 
                                name="photo" 
                                accept="image/jpeg, image/png, image/jpg"
                                class="@error('photo') is-invalid @enderror"
                                required
                                style="padding: 0.5rem;"
                            >
                            <span class="input-hint">Format yang didukung: JPG, JPEG, PNG. Ukuran maksimal 2MB.</span>
                            @error('photo')
                                <div class="error-message">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12" stroke="#fff" stroke-width="2"/><line x1="12" y1="16" x2="12.01" y2="16" stroke="#fff" stroke-width="2"/></svg>
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <!-- Tempat Lahir -->
                        <div class="form-group">
                            <label for="place_of_birth">Tempat Lahir <span class="required">*</span></label>
                            <input 
                                type="text" 
                                id="place_of_birth" 
                                name="place_of_birth" 
                                value="{{ old('place_of_birth') }}" 
                                placeholder="Contoh: Jakarta"
                                class="@error('place_of_birth') is-invalid @enderror"
                                required
                            >
                            @error('place_of_birth')
                                <div class="error-message">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12" stroke="#fff" stroke-width="2"/><line x1="12" y1="16" x2="12.01" y2="16" stroke="#fff" stroke-width="2"/></svg>
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <!-- Tanggal Lahir -->
                        <div class="form-group">
                            <label for="date_of_birth">Tanggal Lahir <span class="required">*</span></label>
                            <input 
                                type="date" 
                                id="date_of_birth" 
                                name="date_of_birth" 
                                value="{{ old('date_of_birth') }}" 
                                max="{{ date('Y-m-d') }}"
                                class="@error('date_of_birth') is-invalid @enderror"
                                required
                            >
                            @error('date_of_birth')
                                <div class="error-message">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12" stroke="#fff" stroke-width="2"/><line x1="12" y1="16" x2="12.01" y2="16" stroke="#fff" stroke-width="2"/></svg>
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Section 2: Pekerjaan & Kontak -->
                <div class="form-section">
                    <div class="section-title">
                        <span class="section-number">2</span>
                        Pekerjaan & Kontak
                    </div>

                    <div class="grid-2">
                        <!-- Kategori Pekerjaan (Enum: UMUM, PELAJAR, MAHASISWA) -->
                        <div class="form-group">
                            <label for="job_category">Kategori Pekerjaan <span class="required">*</span></label>
                            <select 
                                id="job_category" 
                                name="job_category" 
                                class="@error('job_category') is-invalid @enderror"
                                required
                            >
                                <option value="" disabled {{ old('job_category') ? '' : 'selected' }}>-- Pilih Kategori --</option>
                                <option value="UMUM" {{ old('job_category') === 'UMUM' ? 'selected' : '' }}>UMUM</option>
                                <option value="PELAJAR" {{ old('job_category') === 'PELAJAR' ? 'selected' : '' }}>PELAJAR</option>
                                <option value="MAHASISWA" {{ old('job_category') === 'MAHASISWA' ? 'selected' : '' }}>MAHASISWA</option>
                            </select>
                            @error('job_category')
                                <div class="error-message">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12" stroke="#fff" stroke-width="2"/><line x1="12" y1="16" x2="12.01" y2="16" stroke="#fff" stroke-width="2"/></svg>
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <!-- Nama Institusi / Sekolah / Kampus / Kantor -->
                        <div class="form-group">
                            <label for="institution_name">Nama Sekolah / Kampus / Instansi <span class="required">*</span></label>
                            <input 
                                type="text" 
                                id="institution_name" 
                                name="institution_name" 
                                value="{{ old('institution_name') }}" 
                                placeholder="Contoh: SMA 1 / Universitas Indonesia / PT Mandiri"
                                class="@error('institution_name') is-invalid @enderror"
                                required
                            >
                            @error('institution_name')
                                <div class="error-message">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12" stroke="#fff" stroke-width="2"/><line x1="12" y1="16" x2="12.01" y2="16" stroke="#fff" stroke-width="2"/></svg>
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <!-- Nomor Telepon / WA -->
                        <div class="form-group full-width">
                            <label for="phone_number">Nomor Telepon / WhatsApp <span class="required">*</span></label>
                            <input 
                                type="text" 
                                id="phone_number" 
                                name="phone_number" 
                                value="{{ old('phone_number') }}" 
                                placeholder="Contoh: 081234567890"
                                class="@error('phone_number') is-invalid @enderror"
                                required
                            >
                            <span class="input-hint">Digunakan untuk konfirmasi peminjaman buku dan notifikasi batas pengembalian.</span>
                            @error('phone_number')
                                <div class="error-message">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12" stroke="#fff" stroke-width="2"/><line x1="12" y1="16" x2="12.01" y2="16" stroke="#fff" stroke-width="2"/></svg>
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Section 3: Data Alamat -->
                <div class="form-section">
                    <div class="section-title">
                        <span class="section-number">3</span>
                        Data Domisili & Alamat
                    </div>

                    <!-- Alamat KTP -->
                    <div class="form-group">
                        <label for="ktp_address">Alamat Sesuai KTP <span class="required">*</span></label>
                        <textarea 
                            id="ktp_address" 
                            name="ktp_address" 
                            rows="2" 
                            placeholder="Tuliskan alamat lengkap beserta RT/RW, Kelurahan, Kecamatan, Kota"
                            class="@error('ktp_address') is-invalid @enderror"
                            required
                        >{{ old('ktp_address') }}</textarea>
                        @error('ktp_address')
                            <div class="error-message">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12" stroke="#fff" stroke-width="2"/><line x1="12" y1="16" x2="12.01" y2="16" stroke="#fff" stroke-width="2"/></svg>
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <!-- Alamat Tinggal Saat Ini / Domisili -->
                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                            <label for="residential_address" style="margin-bottom: 0;">Alamat Tinggal Saat Ini (Domisili) <span class="required">*</span></label>
                            <label class="same-address-check" for="copyAddressCheck">
                                <input type="checkbox" id="copyAddressCheck">
                                Sama dengan alamat KTP
                            </label>
                        </div>
                        <textarea 
                            id="residential_address" 
                            name="residential_address" 
                            rows="2" 
                            placeholder="Tuliskan alamat tempat Anda tinggal saat ini"
                            class="@error('residential_address') is-invalid @enderror"
                            style="margin-top: 0.45rem;"
                            required
                        >{{ old('residential_address') }}</textarea>
                        @error('residential_address')
                            <div class="error-message">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12" stroke="#fff" stroke-width="2"/><line x1="12" y1="16" x2="12.01" y2="16" stroke="#fff" stroke-width="2"/></svg>
                                {{ $message }}
                            </div>
                        @enderror
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="form-actions">
                    <a href="{{ url('/') }}" class="btn-cancel">Batal</a>
                    <button type="submit" class="btn-submit" id="btnSubmit">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                        Selesaikan Pendaftaran Anggota
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const copyCheck = document.getElementById('copyAddressCheck');
        const ktpTextarea = document.getElementById('ktp_address');
        const resTextarea = document.getElementById('residential_address');
        const form = document.getElementById('memberRegisterForm');
        const submitBtn = document.getElementById('btnSubmit');

        // Copy KTP address to residential address when checkbox checked
        if (copyCheck && ktpTextarea && resTextarea) {
            copyCheck.addEventListener('change', function () {
                if (this.checked) {
                    resTextarea.value = ktpTextarea.value;
                }
            });

            ktpTextarea.addEventListener('input', function () {
                if (copyCheck.checked) {
                    resTextarea.value = this.value;
                }
            });
        }

        // Prevent double submit and show loading indicator
        if (form && submitBtn) {
            form.addEventListener('submit', function () {
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.75';
                submitBtn.innerHTML = `
                    <svg style="animation: spin 1s linear infinite; width: 18px; height: 18px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle>
                        <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"></path>
                    </svg>
                    Menyimpan data...
                `;
            });
        }
    });
</script>
<style>
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
</style>
</body>
</html>
