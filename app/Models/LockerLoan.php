<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LockerLoan extends Model
{
    use HasFactory;

    protected $table = 'locker_loans';

    protected $fillable = [
        'loan_number',
        'member_id',
        'locker_id',
        'deposit_type',
        'borrow_date',
        'return_date',
        'violation_note',
        'status',
        'processed_by_user_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'borrow_date' => 'datetime',
            'return_date' => 'datetime',
        ];
    }

    /**
     * Generate unique Locker Loan Number format: LCK-YYYYMMDD-XXXX
     */
    public static function generateLoanNumber(): string
    {
        $prefix = 'LCK-' . now()->format('Ymd') . '-';
        $last = static::where('loan_number', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->first();

        $sequence = 1;
        if ($last) {
            $lastSequence = (int) substr($last->loan_number, strlen($prefix));
            $sequence = $lastSequence + 1;
        }

        do {
            $candidate = $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $exists = static::where('loan_number', $candidate)->exists();
            if ($exists) {
                $sequence++;
            }
        } while ($exists);

        return $candidate;
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function locker(): BelongsTo
    {
        return $this->belongsTo(Locker::class, 'locker_id');
    }

    public function processedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'processed_by_user_id');
    }
}
