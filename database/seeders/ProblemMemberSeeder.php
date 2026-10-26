<?php

namespace Database\Seeders;

use App\Models\Catalog;
use App\Models\Collection;
use App\Models\Member;
use App\Models\MemberCart;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ProblemMemberSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Featured Member 1: I Putu Gede Arimbawa
        $m1 = Member::firstOrCreate(
            ['member_no' => 'AGT-2024-00812'],
            [
                'name'                => 'I Putu Gede Arimbawa',
                'gender'              => 'L',
                'date_of_birth'       => Carbon::now()->subYears(21)->format('Y-m-d'),
                'place_of_birth'      => 'Buleleng',
                'job_category'        => 'MAHASISWA',
                'institution_name'    => 'Universitas Pendidikan Ganesha',
                'phone_number'        => '0819-3542-8819',
                'email'               => 'arimbawa.putu@undiksha.ac.id',
                'password'            => Hash::make('password'),
                'ktp_address'         => 'Jl. Udayana No. 11, Singaraja, Buleleng',
                'residential_address' => 'Jl. Udayana No. 11, Singaraja, Buleleng',
                'status'              => 'Akses Dibekukan',
                'valid_until'         => Carbon::now()->addYears(3),
            ]
        );

        // 2. Featured Member 2: Kadek Wahyu Praditya
        $m2 = Member::firstOrCreate(
            ['member_no' => 'AGT-2025-04491'],
            [
                'name'                => 'Kadek Wahyu Praditya',
                'gender'              => 'L',
                'date_of_birth'       => Carbon::now()->subYears(14)->format('Y-m-d'),
                'place_of_birth'      => 'Singaraja',
                'job_category'        => 'PELAJAR',
                'institution_name'    => 'SMP Negeri 1 Singaraja',
                'phone_number'        => '0857-3911-0422',
                'email'               => 'wahyu.praditya@smp1singaraja.sch.id',
                'password'            => Hash::make('password'),
                'ktp_address'         => 'Jl. Gajah Mada No. 4, Singaraja',
                'residential_address' => 'Jl. Gajah Mada No. 4, Singaraja',
                'status'              => 'Peringatan ke-2',
                'valid_until'         => Carbon::now()->addYears(4),
            ]
        );

        // 3. Featured Member 3: Dewa Nyoman Sukadana, S.Sos
        $m3 = Member::firstOrCreate(
            ['member_no' => 'AGT-2021-00129'],
            [
                'name'                => 'Dewa Nyoman Sukadana, S.Sos',
                'gender'              => 'L',
                'date_of_birth'       => Carbon::now()->subYears(42)->format('Y-m-d'),
                'place_of_birth'      => 'Sukasada',
                'job_category'        => 'UMUM',
                'institution_name'    => 'Bappeda Kabupaten Buleleng',
                'phone_number'        => '0812-4688-9081',
                'email'               => 'dewa.sukadana@bulelengkab.go.id',
                'password'            => Hash::make('password'),
                'ktp_address'         => 'Komp. Pemda Buleleng Blok B-12, Singaraja',
                'residential_address' => 'Komp. Pemda Buleleng Blok B-12, Singaraja',
                'status'              => 'Dalam Mediasi',
                'valid_until'         => Carbon::now()->addYears(2),
            ]
        );

        // 4. Featured Member 4: Ni Kadek Ayu Mirah Kusuma
        $m4 = Member::firstOrCreate(
            ['member_no' => 'AGT-2025-01889'],
            [
                'name'                => 'Ni Kadek Ayu Mirah Kusuma',
                'gender'              => 'P',
                'date_of_birth'       => Carbon::now()->subYears(20)->format('Y-m-d'),
                'place_of_birth'      => 'Banyuning',
                'job_category'        => 'MAHASISWA',
                'institution_name'    => 'STAHN Mpu Kuturan Singaraja',
                'phone_number'        => '0877-6288-3341',
                'email'               => 'mirah.kusuma@stahnmpukuturan.ac.id',
                'password'            => Hash::make('password'),
                'ktp_address'         => 'Jl. Pulau Komodo Gg. Melati No. 8, Singaraja',
                'residential_address' => 'Jl. Pulau Komodo Gg. Melati No. 8, Singaraja',
                'status'              => 'Akses Dibekukan',
                'valid_until'         => Carbon::now()->addYears(4),
            ]
        );

        // 5. Featured Member 5: Komang Agus Triadi Putra
        $m5 = Member::firstOrCreate(
            ['member_no' => 'AGT-2023-01103'],
            [
                'name'                => 'Komang Agus Triadi Putra',
                'gender'              => 'L',
                'date_of_birth'       => Carbon::now()->subYears(28)->format('Y-m-d'),
                'place_of_birth'      => 'Sukasada',
                'job_category'        => 'UMUM',
                'institution_name'    => 'Wiraswasta Kec. Sukasada',
                'phone_number'        => '0821-0000-0000', // Invalid / flagged
                'email'               => 'agus.triadi@gmail.com',
                'password'            => Hash::make('password'),
                'ktp_address'         => 'Desa Panji Anom, Kec. Sukasada, Kab. Buleleng',
                'residential_address' => 'Desa Panji Anom, Kec. Sukasada, Kab. Buleleng',
                'status'              => 'Menunggu Konfirmasi',
                'valid_until'         => Carbon::now()->addYears(3),
            ]
        );

        // Ensure MemberCart records exist for the 5 featured rows
        $featuredCases = [
            [
                'member_id'          => $m1->id,
                'problem_type'       => 'BUKU_HILANG',
                'item_title'         => 'Pengantar Bioteknologi Tropis & Hayati Buleleng',
                'item_call_number'   => '570.2/ARI/p',
                'item_barcode'       => 'EK-01994',
                'fine_amount'        => 145000,
                'paid_amount'        => 0,
                'handled_by'         => 'Luh Putu Suastini',
                'action_status'      => 'AKSES_DIBEKUKAN',
                'action_notes'       => 'Menunggu buku pengganti',
                'reported_date'      => '2026-08-12',
                'late_duration_text' => '35 Hari Berlalu',
                'description'        => 'Buku hilang saat masa peminjaman. Pemustaka wajib mengganti dengan buku edisi sama atau membayar biaya ganti rugi Rp 145.000.',
                'followup_history'   => [
                    [
                        'date'    => '12 Agustus 2026, 09:30',
                        'officer' => 'Luh Putu Suastini',
                        'action'  => 'Pelaporan Buku Hilang',
                        'status'  => 'AKSES_DIBEKUKAN',
                        'notes'   => 'Pemustaka melaporkan buku hilang di perjalanan.',
                    ],
                    [
                        'date'    => '20 Agustus 2026, 11:15',
                        'officer' => 'Luh Putu Suastini',
                        'action'  => 'Surat Tagihan Ganti Rugi',
                        'status'  => 'AKSES_DIBEKUKAN',
                        'notes'   => 'Surat penetapan penggantian koleksi diserahkan.',
                    ],
                ],
                'is_resolved'        => false,
            ],
            [
                'member_id'          => $m2->id,
                'problem_type'       => 'TELAT',
                'item_title'         => 'Matematika Nusantara Kelas VIII & 1 Lainnya',
                'item_call_number'   => '510/PRA/m',
                'item_barcode'       => 'EK-04421',
                'fine_amount'        => 48000,
                'paid_amount'        => 0,
                'handled_by'         => 'Ketut Dharmayasa',
                'action_status'      => 'PERINGATAN_2',
                'action_notes'       => 'Konfirmasi pihak sekolah pending',
                'reported_date'      => '2026-08-24',
                'late_duration_text' => 'Surat Peringatan 2 Terkirim',
                'description'        => 'Keterlambatan 24 hari pengembalian 2 buku paket pelajaran. Jatuh tempo: 23 Agustus 2026. Tarif denda: Rp 1.000/hari per buku.',
                'followup_history'   => [
                    [
                        'date'    => '24 Agustus 2026, 10:00',
                        'officer' => 'Ketut Dharmayasa',
                        'action'  => 'Peringatan ke-1 (WhatsApp)',
                        'status'  => 'PERINGATAN_1',
                        'notes'   => 'Notifikasi via WA terkirim ke wali murid.',
                    ],
                    [
                        'date'    => '05 September 2026, 14:00',
                        'officer' => 'Ketut Dharmayasa',
                        'action'  => 'Surat Peringatan ke-2',
                        'status'  => 'PERINGATAN_2',
                        'notes'   => 'Surat fisik dikirimkan ke pihak SMPN 1 Singaraja.',
                    ],
                ],
                'is_resolved'        => false,
            ],
            [
                'member_id'          => $m3->id,
                'problem_type'       => 'DENDA_MENUMPUK',
                'item_title'         => 'Manajemen Kebijakan Publik Daerah di Buleleng',
                'item_call_number'   => '351/SUK/m',
                'item_barcode'       => 'EK-00388',
                'fine_amount'        => 168000,
                'paid_amount'        => 50000,
                'handled_by'         => 'Ni Made Sariani',
                'action_status'      => 'DALAM_MEDIASI',
                'action_notes'       => 'Sisa cicilan: Rp 118.000',
                'reported_date'      => '2026-07-05',
                'late_duration_text' => 'Cicilan 1: Rp 50.000',
                'description'        => '3 Koleksi Buku Balik Terlambat, denda tertunggak belum lunas kasir. Telah disepakati skema cicilan 3 tahap.',
                'followup_history'   => [
                    [
                        'date'         => '05 Juli 2026, 13:45',
                        'officer'      => 'Ni Made Sariani',
                        'action'       => 'Mediasi Kasir & Cicilan 1',
                        'payment_made' => 50000,
                        'status'       => 'DALAM_MEDIASI',
                        'notes'        => 'Pembayaran cicilan pertama Rp 50.000 diterima di loket kasir sirkulasi.',
                    ]
                ],
                'is_resolved'        => false,
            ],
            [
                'member_id'          => $m4->id,
                'problem_type'       => 'KOLEKSI_RUSAK',
                'item_title'         => 'Tata Upacara & Filosofi Budaya Bali Utara',
                'item_call_number'   => '294.5/KUS/t',
                'item_barcode'       => 'EK-00811',
                'fine_amount'        => 75000,
                'paid_amount'        => 0,
                'handled_by'         => 'Gede Windu Yasa',
                'action_status'      => 'AKSES_DIBEKUKAN',
                'action_notes'       => 'Sanksi: Tidak Boleh Pinjam',
                'reported_date'      => '2026-09-01',
                'late_duration_text' => 'Buku Disita Bag. Sirkulasi',
                'description'        => 'Kerusakan: Halaman 45-80 basah robek dan tidak terbaca. Biaya perbaikan jilid ulang dan denda kerusakan Rp 75.000.',
                'followup_history'   => [
                    [
                        'date'    => '01 September 2026, 11:20',
                        'officer' => 'Gede Windu Yasa',
                        'action'  => 'Penyitaan Koleksi & Pemeriksaan Fisik',
                        'status'  => 'AKSES_DIBEKUKAN',
                        'notes'   => 'Eksemplar EK-00811 dikarantina untuk perbaikan konservasi naskah.',
                    ]
                ],
                'is_resolved'        => false,
            ],
            [
                'member_id'          => $m5->id,
                'problem_type'       => 'ANOMALI_IDENTITAS',
                'item_title'         => 'Kloning NIK Kependudukan Buleleng',
                'item_call_number'   => 'IDENTITAS/001',
                'item_barcode'       => 'SIAK-BLL-09',
                'fine_amount'        => 0,
                'paid_amount'        => 0,
                'handled_by'         => 'Ridwan Surasta',
                'action_status'      => 'MENUNGGU_KONFIRMASI',
                'action_notes'       => 'Wajib hadir langsung ke DAPD',
                'reported_date'      => '2026-09-10',
                'late_duration_text' => 'Verifikasi Berkas KTP',
                'description'        => 'Terdeteksi 2 akun terdaftar dengan NIK yang sama pada sinkronisasi SIAK DAPD. No WhatsApp tidak valid. Pemustaka wajib verifikasi fisik dengan membawa e-KTP.',
                'followup_history'   => [
                    [
                        'date'    => '10 September 2026, 08:30',
                        'officer' => 'Ridwan Surasta',
                        'action'  => 'Anomali Duplikasi NIK Terdeteksi',
                        'status'  => 'MENUNGGU_KONFIRMASI',
                        'notes'   => 'Sistem memblokir sementara pembuatan kartu ganda.',
                    ]
                ],
                'is_resolved'        => false,
            ],
        ];

        foreach ($featuredCases as $data) {
            $existing = MemberCart::where('member_id', $data['member_id'])
                ->where('problem_type', $data['problem_type'])
                ->first();
            if (!$existing) {
                MemberCart::create($data);
            }
        }

        // Additional sample members to complete the 28 cases (7 Umum, 12 Pelajar, 9 Mahasiswa)
        // Currently we have:
        // Featured: 2 Umum ($m3, $m5), 1 Pelajar ($m2), 2 Mahasiswa ($m1, $m4)
        // Needed:
        // Umum: 5 more
        // Pelajar: 11 more
        // Mahasiswa: 7 more

        // Seed 5 more Umum members & cases
        $umumNames = [
            ['Putu Hendra Wijaya', 'Kantor Camat Buleleng', '0813-3891-2201'],
            ['Ketut Sri Wahyuni', 'RSUD Kabupaten Buleleng', '0852-3788-4112'],
            ['Made Agus Suputra', 'Dinas Pariwisata Kab. Buleleng', '0878-6311-0023'],
            ['Komang Ari Prasetya', 'Wiraswasta Kota Singaraja', '0819-3321-7788'],
            ['Luh Dewi Anggreni', 'Kantor Pos Singaraja', '0857-3890-4411'],
        ];

        foreach ($umumNames as $idx => $item) {
            $memNo = 'AGT-2023-' . str_pad((string)(200 + $idx), 5, '0', STR_PAD_LEFT);
            $mem = Member::firstOrCreate(
                ['member_no' => $memNo],
                [
                    'name'                => $item[0],
                    'gender'              => ($idx % 2 === 0) ? 'L' : 'P',
                    'date_of_birth'       => Carbon::now()->subYears(30 + $idx)->format('Y-m-d'),
                    'place_of_birth'      => 'Buleleng',
                    'job_category'        => 'UMUM',
                    'institution_name'    => $item[1],
                    'phone_number'        => $item[2],
                    'password'            => Hash::make('password'),
                    'ktp_address'         => 'Kabupaten Buleleng',
                    'residential_address' => 'Kabupaten Buleleng',
                    'status'              => 'Akses Dibekukan',
                    'valid_until'         => Carbon::now()->addYears(3),
                ]
            );

            if (!MemberCart::where('member_id', $mem->id)->exists()) {
                MemberCart::create([
                    'member_id'          => $mem->id,
                    'problem_type'       => ($idx % 2 === 0) ? 'TELAT' : 'DENDA_MENUMPUK',
                    'item_title'         => 'Hukum Tata Usaha Negara Buleleng Vol. ' . ($idx + 1),
                    'item_call_number'   => '340/HUK/' . ($idx + 1),
                    'item_barcode'       => 'EK-098' . $idx,
                    'fine_amount'        => 35000 + ($idx * 15000),
                    'paid_amount'        => 0,
                    'handled_by'         => 'Ni Made Sariani',
                    'action_status'      => 'AKSES_DIBEKUKAN',
                    'action_notes'       => 'Peminjaman buku tertahan',
                    'reported_date'      => Carbon::now()->subDays(10 + $idx)->format('Y-m-d'),
                    'late_duration_text' => 'Telat ' . (10 + $idx) . ' Hari',
                    'description'        => 'Terlambat mengembalikan koleksi perpustakaan.',
                    'is_resolved'        => false,
                ]);
            }
        }

        // Seed 11 more Pelajar members & cases
        $pelajarNames = [
            ['I Komang Satria Wibawa', 'SMP Negeri 2 Singaraja', '0812-4455-6601'],
            ['Ni Luh Putu Sintia Dewi', 'SMAN 1 Singaraja', '0857-1122-3344'],
            ['Gede Bagus Mahendra', 'SMKN 1 Singaraja', '0878-9988-7766'],
            ['Made Dwi Artha', 'SMP Negeri 3 Singaraja', '0819-2233-4455'],
            ['Ketut Ayu Lestari', 'SMAN 2 Singaraja', '0852-3344-5566'],
            ['Putu Rian Aditya', 'SMP Negeri 1 Sukasada', '0813-7766-5544'],
            ['Kadek Dian Safitri', 'SMAN 3 Singaraja', '0877-6655-4433'],
            ['Komang Yudha Pratama', 'SMP Triamerta Singaraja', '0858-1122-3344'],
            ['Luh Ketut Melati', 'SMK Negeri 2 Singaraja', '0812-9900-8877'],
            ['Gede Surya Pranata', 'SMP Negeri 4 Singaraja', '0878-5544-3322'],
            ['Ni Made Cantika Putri', 'SMAN 4 Singaraja', '0857-4433-2211'],
        ];

        foreach ($pelajarNames as $idx => $item) {
            $memNo = 'AGT-2025-' . str_pad((string)(500 + $idx), 5, '0', STR_PAD_LEFT);
            $mem = Member::firstOrCreate(
                ['member_no' => $memNo],
                [
                    'name'                => $item[0],
                    'gender'              => ($idx % 2 === 0) ? 'L' : 'P',
                    'date_of_birth'       => Carbon::now()->subYears(13 + ($idx % 5))->format('Y-m-d'),
                    'place_of_birth'      => 'Singaraja',
                    'job_category'        => 'PELAJAR',
                    'institution_name'    => $item[1],
                    'phone_number'        => $item[2],
                    'password'            => Hash::make('password'),
                    'ktp_address'         => 'Kec. Buleleng, Kab. Buleleng',
                    'residential_address' => 'Kec. Buleleng, Kab. Buleleng',
                    'status'              => ($idx % 3 === 0) ? 'Peringatan ke-1' : 'Peringatan ke-2',
                    'valid_until'         => Carbon::now()->addYears(3),
                ]
            );

            if (!MemberCart::where('member_id', $mem->id)->exists()) {
                MemberCart::create([
                    'member_id'          => $mem->id,
                    'problem_type'       => ($idx % 3 === 0) ? 'TELAT' : (($idx % 3 === 1) ? 'BUKU_HILANG' : 'KOLEKSI_RUSAK'),
                    'item_title'         => 'Buku Paket Siswa Buleleng Edisi ' . ($idx + 1),
                    'item_call_number'   => '371/SIS/' . ($idx + 1),
                    'item_barcode'       => 'EK-055' . $idx,
                    'fine_amount'        => 20000 + ($idx * 5000),
                    'paid_amount'        => 0,
                    'handled_by'         => 'Ketut Dharmayasa',
                    'action_status'      => ($idx % 3 === 0) ? 'PERINGATAN_1' : 'PERINGATAN_2',
                    'action_notes'       => 'Teguran sekolah',
                    'reported_date'      => Carbon::now()->subDays(5 + $idx)->format('Y-m-d'),
                    'late_duration_text' => 'Telat ' . (5 + $idx) . ' Hari',
                    'description'        => 'Peminjaman buku sekolah terlewat masa tenggang.',
                    'is_resolved'        => false,
                ]);
            }
        }

        // Seed 7 more Mahasiswa members & cases
        $mahasiswaNames = [
            ['I Made Aditya Darmawan', 'Universitas Pendidikan Ganesha', '0812-7711-8899'],
            ['Ni Putu Eka Pratiwi', 'Universitas Panji Sakti', '0857-8899-0011'],
            ['Gede Arya Kusuma', 'STAHN Mpu Kuturan Singaraja', '0878-1100-2233'],
            ['Kadek Febrianti', 'Undiksha Singaraja', '0819-4455-6677'],
            ['Komang Widiantara', 'Universitas Panji Sakti', '0852-6677-8899'],
            ['Luh Putu Risma Dewi', 'STAHN Mpu Kuturan Singaraja', '0813-9988-7711'],
            ['Ketut Budi Artawan', 'Undiksha Singaraja', '0877-2233-4411'],
        ];

        foreach ($mahasiswaNames as $idx => $item) {
            $memNo = 'AGT-2024-' . str_pad((string)(800 + $idx), 5, '0', STR_PAD_LEFT);
            $mem = Member::firstOrCreate(
                ['member_no' => $memNo],
                [
                    'name'                => $item[0],
                    'gender'              => ($idx % 2 === 0) ? 'L' : 'P',
                    'date_of_birth'       => Carbon::now()->subYears(20 + ($idx % 4))->format('Y-m-d'),
                    'place_of_birth'      => 'Singaraja',
                    'job_category'        => 'MAHASISWA',
                    'institution_name'    => $item[1],
                    'phone_number'        => $item[2],
                    'password'            => Hash::make('password'),
                    'ktp_address'         => 'Kab. Buleleng',
                    'residential_address' => 'Kab. Buleleng',
                    'status'              => 'Akses Dibekukan',
                    'valid_until'         => Carbon::now()->addYears(3),
                ]
            );

            if (!MemberCart::where('member_id', $mem->id)->exists()) {
                MemberCart::create([
                    'member_id'          => $mem->id,
                    'problem_type'       => ($idx % 2 === 0) ? 'BUKU_HILANG' : 'KOLEKSI_RUSAK',
                    'item_title'         => 'Metodologi Penelitian Akademik Buleleng Jilid ' . ($idx + 1),
                    'item_call_number'   => '001.4/MET/' . ($idx + 1),
                    'item_barcode'       => 'EK-077' . $idx,
                    'fine_amount'        => 60000 + ($idx * 15000),
                    'paid_amount'        => ($idx === 1) ? 30000 : 0,
                    'handled_by'         => 'Luh Putu Suastini',
                    'action_status'      => ($idx === 1) ? 'DALAM_MEDIASI' : 'AKSES_DIBEKUKAN',
                    'action_notes'       => ($idx === 1) ? 'Cicilan ganti rugi berjalan' : 'Menunggu penyelesaian',
                    'reported_date'      => Carbon::now()->subDays(8 + $idx)->format('Y-m-d'),
                    'late_duration_text' => 'Tertahan ' . (8 + $idx) . ' Hari',
                    'description'        => 'Koleksi dalam masa tanggungan pemustaka.',
                    'is_resolved'        => false,
                ]);
            }
        }
    }
}
