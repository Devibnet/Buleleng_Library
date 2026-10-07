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

    protected $fillable = [
        'visit_type',
        'member_id',
        'visitor_name',
        'phone_number',
        'institution_name',
        'institution_address',
        'institution_email',
        'address',
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
