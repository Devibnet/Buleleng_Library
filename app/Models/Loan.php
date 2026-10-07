<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    use HasFactory;

    protected $table = 'loans';

    const STATUS_PENDING_CHECK   = 'PENDING_CHECK';
    const STATUS_READY_FOR_PICKUP = 'READY_FOR_PICKUP';
    const STATUS_UNAVAILABLE      = 'UNAVAILABLE';
    const STATUS_RESERVED         = 'RESERVED';
    const STATUS_BORROWED         = 'BORROWED';
    const STATUS_RETURNED         = 'RETURNED';
    const STATUS_OVERDUE          = 'OVERDUE';
    const STATUS_CANCELLED        = 'CANCELLED';
    const STATUS_EXPIRED          = 'EXPIRED';
    const STATUS_LOST             = 'LOST';

    protected $fillable = [
        'loan_number',
        'reservation_code',
        'member_id',
        'collection_id',
        'loan_date',
        'borrow_date',
        'due_date',
        'return_date',
        'pickup_deadline',
        'late_days',
        'extension_count',
        'status',
        'fine_amount',
        'created_by_user_id',
        'returned_by_user_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pickup_deadline' => 'datetime',
            'borrow_date'     => 'datetime',
            'loan_date'       => 'date',
            'due_date'        => 'date',
            'return_date'     => 'date',
            'late_days'       => 'integer',
            'extension_count' => 'integer',
            'fine_amount'     => 'decimal:2',
        ];
    }

    /**
     * Generate unique Loan Number format: LON-YYYYMMDD-XXXX
     */
    public static function generateLoanNumber(): string
    {
        $prefix = 'LON-' . now()->format('Ymd') . '-';
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

    /**
     * Generate unique Reservation Code format: RSV-YYYYMMDD-XXXX
     */
    public static function generateReservationCode(): string
    {
        $prefix = 'RSV-' . now()->format('Ymd') . '-';
        $last = static::where('reservation_code', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->first();

        $sequence = 1;
        if ($last) {
            $lastSequence = (int) substr($last->reservation_code, strlen($prefix));
            $sequence = $lastSequence + 1;
        }

        do {
            $candidate = $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $exists = static::where('reservation_code', $candidate)->exists();
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

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class, 'collection_id');
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_user_id');
    }

    public function returnedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'returned_by_user_id');
    }

    public function loanExtensions(): HasMany
    {
        return $this->hasMany(LoanExtension::class, 'loan_id');
    }
}
