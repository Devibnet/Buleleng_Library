<?php

namespace App\Services;

use App\Models\Guestbook;
use App\Models\Member;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class GuestbookService
{
    /**
     * 1. KUNJUNGAN ANGGOTA (recordMemberVisit)
     */
    public function recordMemberVisit(string $memberNo): Guestbook
    {
        $member = Member::where('member_no', $memberNo)
            ->orWhere('id', $memberNo)
            ->first();

        if (! $member) {
            throw new ModelNotFoundException("Anggota dengan nomor/ID '{$memberNo}' tidak ditemukan di sistem.");
        }

        $maleCount = (isset($member->gender) && strtoupper($member->gender) === 'M') ? 1 : 0;
        $femaleCount = (isset($member->gender) && strtoupper($member->gender) === 'F') ? 1 : 0;

        // Jika gender di member belum diset, default ke 1 male jika 0
        if ($maleCount === 0 && $femaleCount === 0) {
            $maleCount = 1;
        }

        return Guestbook::create([
            'visit_type'          => 'MEMBER',
            'member_id'           => $member->id,
            'visitor_name'        => $member->name,
            'phone_number'        => $member->phone_number,
            'address'             => $member->residential_address,
            'institution_name'    => $member->institution_name,
            'total_personnel'     => 1,
            'gender_male_count'   => $maleCount,
            'gender_female_count' => $femaleCount,
            'visit_date'          => now(),
        ]);
    }

    /**
     * 2. KUNJUNGAN NON-ANGGOTA (recordNonMemberVisit)
     */
    public function recordNonMemberVisit(array $data): Guestbook
    {
        $gender = strtoupper($data['gender'] ?? 'M');
        $maleCount = ($gender === 'M') ? 1 : 0;
        $femaleCount = ($gender === 'F') ? 1 : 0;

        return Guestbook::create([
            'visit_type'          => 'NON_MEMBER',
            'member_id'           => null,
            'visitor_name'        => $data['visitor_name'],
            'phone_number'        => $data['phone_number'] ?? null,
            'address'             => $data['address'] ?? null,
            'institution_name'    => $data['institution_name'] ?? null,
            'total_personnel'     => 1,
            'gender_male_count'   => $maleCount,
            'gender_female_count' => $femaleCount,
            'visit_date'          => now(),
        ]);
    }

    /**
     * 3. KUNJUNGAN ROMBONGAN (recordGroupVisit)
     */
    public function recordGroupVisit(array $data): Guestbook
    {
        $total = (int) $data['total_personnel'];
        $male = (int) $data['gender_male_count'];
        $female = (int) $data['gender_female_count'];

        // Validasi: male + female harus sama dengan total_personnel
        if (($male + $female) !== $total) {
            throw new Exception("Jumlah Laki-Laki ({$male}) + Perempuan ({$female}) = " . ($male + $female) . " harus sama dengan Total Personil Rombongan ({$total}).");
        }

        return Guestbook::create([
            'visit_type'          => 'GROUP',
            'member_id'           => null,
            'visitor_name'        => $data['visitor_name'],
            'phone_number'        => $data['phone_number'] ?? null,
            'institution_name'    => $data['institution_name'],
            'institution_address' => $data['institution_address'] ?? null,
            'institution_email'   => $data['institution_email'] ?? null,
            'total_personnel'     => $total,
            'gender_male_count'   => $male,
            'gender_female_count' => $female,
            'job_breakdown'       => $data['job_breakdown'] ?? [],
            'education_breakdown' => $data['education_breakdown'] ?? [],
            'visit_date'          => now(),
        ]);
    }

    /**
     * 4. REKAP & LAPORAN KUNJUNGAN (getReports)
     */
    public function getReports(?string $startDate = null, ?string $endDate = null, ?string $visitType = null, int $perPage = 15): array
    {
        $query = Guestbook::with('member');

        if (! empty($startDate)) {
            $query->whereDate('visit_date', '>=', $startDate);
        }

        if (! empty($endDate)) {
            $query->whereDate('visit_date', '<=', $endDate);
        }

        if (! empty($visitType) && $visitType !== 'ALL') {
            $query->where('visit_type', $visitType);
        }

        // Clone query untuk kalkulasi total statistik agregasi
        $summaryQuery = clone $query;
        $totalVisits = $summaryQuery->count();
        $totalVisitors = (int) $summaryQuery->sum('total_personnel');
        $totalMale = (int) $summaryQuery->sum('gender_male_count');
        $totalFemale = (int) $summaryQuery->sum('gender_female_count');

        // Agregasi Harian (Daily Summary)
        $dailySummary = (clone $query)
            ->select([
                DB::raw('DATE(visit_date) as date'),
                DB::raw('COUNT(*) as total_records'),
                DB::raw('SUM(total_personnel) as total_visitors'),
                DB::raw('SUM(gender_male_count) as male_visitors'),
                DB::raw('SUM(gender_female_count) as female_visitors'),
            ])
            ->groupBy(DB::raw('DATE(visit_date)'))
            ->orderBy(DB::raw('DATE(visit_date)'), 'desc')
            ->get();

        $logs = $query->orderBy('id', 'desc')->paginate($perPage);

        return [
            'summary' => [
                'total_records'  => $totalVisits,
                'total_visitors' => $totalVisitors,
                'total_male'     => $totalMale,
                'total_female'   => $totalFemale,
            ],
            'daily_summary' => $dailySummary,
            'logs'          => $logs,
        ];
    }
}
