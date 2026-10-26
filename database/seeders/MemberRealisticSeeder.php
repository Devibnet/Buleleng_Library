<?php

namespace Database\Seeders;

use App\Models\Member;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MemberRealisticSeeder extends Seeder
{
    public function run(): void
    {
        $members = [
            [
                'member_no'           => 'AGT-2026-00189',
                'nik'                 => '5108051208070001',
                'name'                => 'I Putu Arya Dananjaya',
                'gender'              => 'L',
                'place_of_birth'      => 'Singaraja',
                'date_of_birth'       => '2007-08-12',
                'job_category'        => 'PELAJAR',
                'institution_name'    => 'SMAN 1 Singaraja',
                'max_loans'           => 3,
                'phone_number'        => '812-3488-9012',
                'email'               => 'putu.arya@sman1singaraja.sch.id',
                'ktp_address'         => 'Jl. Udayana No. 11, Kel. Banyuasri, Kec. Buleleng',
                'residential_address' => 'Jl. Udayana No. 11, Kel. Banyuasri, Kec. Buleleng',
                'status'              => 'Aktif Bebas Pustaka',
                'valid_until'         => '2031-09-06',
            ],
            [
                'member_no'           => 'AGT-2026-00190',
                'nik'                 => '5108051904040002',
                'name'                => 'Muhammad Nur Faqih',
                'gender'              => 'L',
                'place_of_birth'      => 'Buleleng',
                'date_of_birth'       => '2004-04-19',
                'job_category'        => 'MAHASISWA',
                'institution_name'    => 'Universitas Pendidikan Ganesha',
                'max_loans'           => 3,
                'phone_number'        => '878-5521-9983',
                'email'               => 'nur.faqih@undiksha.ac.id',
                'ktp_address'         => 'Jl. Ahmad Yani No. 45, Kel. Kaliuntu, Buleleng',
                'residential_address' => 'Jl. Ahmad Yani No. 45, Kel. Kaliuntu, Buleleng',
                'status'              => 'Aktif Bebas Pustaka',
                'valid_until'         => '2031-09-14',
            ],
            [
                'member_no'           => 'AGT-2026-00191',
                'nik'                 => '5108051503840003',
                'name'                => 'Ni Made Sintia Dewi, S.Pd',
                'gender'              => 'P',
                'place_of_birth'      => 'Singaraja',
                'date_of_birth'       => '1984-03-15',
                'job_category'        => 'UMUM',
                'institution_name'    => 'Dinas Pendidikan Kab. Buleleng',
                'max_loans'           => 5,
                'phone_number'        => '813-8981-4452',
                'email'               => 'sintia.dewi@bulelengkab.go.id',
                'ktp_address'         => 'Jl. Veteran No. 23, RT 02 / RW 04, Kel. Kampung Anyar, Kec. Buleleng',
                'residential_address' => 'Jl. Veteran No. 23, Kelurahan Kampung Anyar, Buleleng, Bali',
                'status'              => 'Aktif Bebas Pustaka',
                'valid_until'         => '2031-09-01',
            ],
            [
                'member_no'           => 'AGT-2026-00192',
                'nik'                 => '5108050511950004',
                'name'                => 'Kadek Bayu Wira Pratama',
                'gender'              => 'L',
                'place_of_birth'      => 'Banjar Tegal',
                'date_of_birth'       => '1995-11-05',
                'job_category'        => 'UMUM',
                'institution_name'    => 'PT Telkom Buleleng',
                'max_loans'           => 2,
                'phone_number'        => '819-9941-2091',
                'email'               => 'bayu.wira@telkom.co.id',
                'ktp_address'         => 'Banjar Tegal, Singaraja, Buleleng',
                'residential_address' => 'Banjar Tegal, Singaraja, Buleleng',
                'status'              => 'Aktif Bebas Pustaka',
                'valid_until'         => '2031-09-09',
            ],
            [
                'member_no'           => 'AGT-2026-00193',
                'nik'                 => '5108022207090005',
                'name'                => 'Fatimah Az-Zahra',
                'gender'              => 'P',
                'place_of_birth'      => 'Gerokgak',
                'date_of_birth'       => '2009-07-22',
                'job_category'        => 'PELAJAR',
                'institution_name'    => 'SMP Negeri 2 Singaraja',
                'max_loans'           => 3,
                'phone_number'        => '857-3765-8812',
                'email'               => 'fatimah.azzahra@smp2singaraja.sch.id',
                'ktp_address'         => 'Desa Gerokgak, Kec. Gerokgak, Kab. Buleleng',
                'residential_address' => 'Desa Gerokgak, Kec. Gerokgak, Kab. Buleleng',
                'status'              => 'Aktif Bebas Pustaka',
                'valid_until'         => '2031-09-11',
            ],
        ];

        foreach ($members as $data) {
            Member::updateOrCreate(
                ['member_no' => $data['member_no']],
                array_merge($data, ['password' => Hash::make('atlas123456')])
            );
        }
    }
}
