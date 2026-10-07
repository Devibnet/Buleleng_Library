<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanExtension extends Model
{
    use HasFactory;

    protected $table = 'loan_extensions';

    protected $fillable = [
        'loan_id',
        'previous_due_date',
        'new_due_date',
        'extension_date',
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
            'previous_due_date' => 'date',
            'new_due_date' => 'date',
            'extension_date' => 'date',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class, 'loan_id');
    }

    public function processedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'processed_by_user_id');
    }
}
