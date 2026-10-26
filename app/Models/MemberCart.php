<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberCart extends Model
{
    use HasFactory;

    protected $table = 'member_carts';

    protected $fillable = [
        'member_id',
        'loan_id',
        'collection_id',
        'problem_type',
        'item_title',
        'item_call_number',
        'item_barcode',
        'description',
        'fine_amount',
        'paid_amount',
        'handled_by',
        'action_status',
        'action_notes',
        'reported_date',
        'late_duration_text',
        'followup_history',
        'is_resolved',
        'resolution_notes',
        'resolved_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_resolved'      => 'boolean',
            'reported_date'    => 'date',
            'resolved_at'      => 'datetime',
            'fine_amount'      => 'decimal:2',
            'paid_amount'      => 'decimal:2',
            'followup_history' => 'array',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class, 'loan_id');
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class, 'collection_id');
    }
}
