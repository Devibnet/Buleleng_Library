<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Guestbook extends Model
{
    use HasFactory;

    protected $table = 'guestbooks';

    public const VISIT_TYPE_LABELS = [
        'MEMBER'     => 'Anggota',
        'NON_MEMBER' => 'Non Anggota',
        'GROUP'      => 'Rombongan',
        'INDIVIDUAL' => 'Non Anggota',
    ];

    public const JOB_LABELS = [
        'PNS'        => 'Pegawai Negeri',
        'SWASTA'     => 'Pegawai Swasta',
        'PENELITI'   => 'Peneliti',
        'DOSEN'      => 'Dosen',
        'GURU'       => 'Guru',
        'TNI_POLRI'  => 'TNI / POLRI',
        'PENSIUNAN'  => 'Pensiunan',
        'WIRASWASTA' => 'Wiraswasta',
        'MAHASISWA'  => 'Mahasiswa',
        'PELAJAR'    => 'Pelajar',
        'LAINNYA'    => 'Lainnya',
    ];

    public const EDUCATION_LABELS = [
        'SD'  => 'SD',
        'SMP' => 'SMP (sederajat)',
        'SMA' => 'SMA (sederajat)',
        'D1'  => 'Diploma (D1)',
        'D2'  => 'Diploma (D2)',
        'D3'  => 'Diploma (D3)',
        'S1'  => 'Sarjana (S1)',
        'S2'  => 'Magister (S2)',
        'S3'  => 'Doktor (S3)',
    ];

    // Nama orang: huruf, spasi, dan tanda baca gelar (titik, koma, apostrof, strip)
    public const PERSON_NAME_REGEX = "/^[\pL\s.,'-]+$/u";

    public const PHONE_REGEX = '/^[0-9]+$/';

    protected $fillable = [
        'visit_type',
        'member_id',
        'visitor_name',
        'phone_number',
        'institution_name',
        'institution_address',
        'institution_email',
        'institution_phone',
        'address',
        'job',
        'education',
        'visit_purpose',
        'total_personnel',
        'gender_male_count',
        'gender_female_count',
        'job_breakdown',
        'education_breakdown',
        'visit_date',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_personnel' => 'integer',
            'gender_male_count' => 'integer',
            'gender_female_count' => 'integer',
            'job_breakdown' => 'array',
            'education_breakdown' => 'array',
            'visit_date' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function onsiteReadings(): HasMany
    {
        return $this->hasMany(OnsiteReading::class, 'guestbook_id');
    }
}
