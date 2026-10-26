<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Collection extends Model
{
    use HasFactory;

    protected $table = 'collections';

    protected $fillable = [
        'catalog_id',
        'item_number',
        'barcode_no',
        'rfid_no',
        'exemplar_call_number',
        'procurement_date',
        'source_type',
        'source_name',
        'currency',
        'price',
        'library_location_code',
        'room_location_code',
        'access_type',
        'category',
        'availability_status',
        'physical_condition',
        'is_quarantine',
        'quarantine_reason',
        'quarantine_notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'procurement_date' => 'date',
            'price' => 'decimal:2',
            'is_quarantine' => 'boolean',
        ];
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(Catalog::class, 'catalog_id');
    }

    public function onsiteReadings(): HasMany
    {
        return $this->hasMany(OnsiteReading::class, 'collection_id');
    }

    public function reservedCollections(): HasMany
    {
        return $this->hasMany(ReservedCollection::class, 'collection_id');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class, 'collection_id');
    }
}
