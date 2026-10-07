<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnsiteReading extends Model
{
    use HasFactory;

    protected $table = 'onsite_readings';

    public $timestamps = false;

    protected $fillable = [
        'guestbook_id',
        'member_id',
        'collection_id',
        'read_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function guestbook(): BelongsTo
    {
        return $this->belongsTo(Guestbook::class, 'guestbook_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class, 'collection_id');
    }
}
