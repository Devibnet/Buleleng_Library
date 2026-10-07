<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Anggota - Perpustakaan</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --secondary: #0f172a;
            --card-bg: #ffffff;
            --border-color: #e2e8f0;
            --text-main: #1e293b;
            --text-muted: #64748b;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f8fafc;
            color: var(--text-main);
            min-height: 100vh;
        }

        .navbar {
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .nav-brand {
            font-size: 1.2rem;
            font-weight: 800;
            color: #1e40af;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-user {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary);
        }

        .logout-btn {
            background: none;
            border: 1px solid #cbd5e1;
            padding: 0.45rem 0.95rem;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.85rem;
            font-weight: 600;
            color: #475569;
            cursor: pointer;
            transition: all 0.2s;
        }

        .logout-btn:hover {
            background: #fee2e2;
            color: #b91c1c;
            border-color: #fca5a5;
        }

        .container {
            max-width: 900px;
            margin: 2.5rem auto;
            padding: 0 1rem;
        }

        .alert-success {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
            padding: 1rem 1.25rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
        }

        /* Membership Card */
        .member-card {
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 60%, #3b82f6 100%);
            border-radius: 20px;
            padding: 2.25rem;
            color: #ffffff;
            box-shadow: 0 20px 25px -5px rgba(37, 99, 235, 0.25);
            position: relative;
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .member-card::after {
            content: '';
            position: absolute;
            top: -60px;
            right: -60px;
            width: 220px;
            height: 220px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 50%;
        }

        .card-header-inner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .card-chip {
            font-size: 0.75rem;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            background: rgba(255, 255, 255, 0.2);
            padding: 0.35rem 0.85rem;
            border-radius: 9999px;
            font-weight: 700;
        }

        .member-no-tag {
            font-family: monospace;
            font-size: 1.4rem;
            letter-spacing: 0.08em;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: #fef08a;
        }

        .card-details {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .card-avatar {
            width: 68px;
            height: 68px;
            border-radius: 50%;
            border: 3px solid rgba(255, 255, 255, 0.8);
            object-fit: cover;
            background: #ffffff;
        }

        .card-profile-text h2 {
            font-size: 1.35rem;
            font-weight: 800;
            margin-bottom: 0.25rem;
        }

        .card-profile-text p {
            font-size: 0.9rem;
            color: #dbeafe;
        }

        /* Profile Details Table/Grid */
        .info-panel {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 1.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .info-panel h3 {
            font-size: 1.1rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 1.25rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #f1f5f9;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.25rem;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .info-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .info-val {
            font-size: 0.95rem;
            font-weight: 600;
            color: #1e293b;
        }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="nav-brand">
        📚 Sistem Perpustakaan
    </div>
    <div class="nav-user">
        <img 
            src="{{ $member->photo_path ?? 'https://ui-avatars.com/api/?name=' . urlencode($member->name) . '&background=2563eb&color=fff' }}" 
            alt="Avatar" 
            class="user-avatar"
            referrerpolicy="no-referrer"
        >
        <form action="{{ route('member.logout') }}" method="POST">
            @csrf
            <button type="submit" class="logout-btn">Keluar</button>
        </form>
    </div>
</nav>

<div class="container">
    @if (session('success'))
        <div class="alert-success">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    <!-- Virtual Member Card -->
    <div class="member-card">
        <div class="card-header-inner">
            <span class="card-chip">Kartu Anggota Digital</span>
            <span style="font-size: 0.85rem; font-weight: 600; opacity: 0.9;">PERPUSTAKAAN</span>
        </div>
        <div class="member-no-tag">{{ $member->member_no }}</div>
        <div class="card-details">
            <img 
                src="{{ $member->photo_path ?? 'https://ui-avatars.com/api/?name=' . urlencode($member->name) . '&background=2563eb&color=fff' }}" 
                alt="Avatar" 
                class="card-avatar"
                referrerpolicy="no-referrer"
            >
            <div class="card-profile-text">
                <h2>{{ $member->name }}</h2>
                <p>{{ $member->email }} &bull; <strong style="color: #fef08a;">{{ $member->job_category }}</strong></p>
            </div>
        </div>
    </div>

    <!-- Data Profil Lengkap -->
    <div class="info-panel">
        <h3>Informasi Keanggotaan</h3>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Tempat & Tanggal Lahir</span>
                <span class="info-val">{{ $member->place_of_birth ?? '-' }}, {{ $member->date_of_birth ? $member->date_of_birth->format('d F Y') : '-' }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Nomor WhatsApp/Telepon</span>
                <span class="info-val">{{ $member->phone_number ?? '-' }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Instansi / Sekolah / Kampus</span>
                <span class="info-val">{{ $member->institution_name ?? '-' }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Kategori Pekerjaan</span>
                <span class="info-val">{{ $member->job_category }}</span>
            </div>
            <div class="info-item" style="grid-column: 1 / -1;">
                <span class="info-label">Alamat Sesuai KTP</span>
                <span class="info-val">{{ $member->ktp_address ?? '-' }}</span>
            </div>
            <div class="info-item" style="grid-column: 1 / -1;">
                <span class="info-label">Alamat Domisili (Tinggal Saat Ini)</span>
                <span class="info-val">{{ $member->residential_address ?? '-' }}</span>
            </div>
        </div>
    </div>
</div>

</body>
</html>
