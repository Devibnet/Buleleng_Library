<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Catalog extends Model
{
    use HasFactory;

    protected $table = 'catalogs';

    protected $fillable = [
        'material_type',
        'media_type',
        'title',
        'subtitle',
        'statement_of_responsibility',
        'author_main',
        'author_corporate',
        'author_additional_person',
        'author_additional_corporate',
        'edition',
        'publish_city',
        'publisher',
        'publish_year',
        'pages',
        'dimension',
        'isbn',
        'issn',
        'ismn',
        'ddc_number',
        'catalog_call_number',
        'subject_topic',
        'language',
        'abstract',
        'serial_edition',
        'serial_publish_date',
        'serial_accompanying_material',
        'serial_notes',
        'total_copies',
        'available_copies',
        'is_quarantine',
        'quarantine_reason',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'serial_publish_date' => 'date',
            'is_quarantine' => 'boolean',
        ];
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class, 'catalog_id');
    }

    /**
     * Hitung ulang total dan ketersediaan stok fisik eksemplar.
     */
    public function recalculateCopies(): void
    {
        $total = $this->collections()->count();
        $available = $this->collections()
            ->where('availability_status', 'AVAILABLE')
            ->where('is_quarantine', false)
            ->where(function ($q) {
                $q->whereNull('physical_condition')
                  ->orWhereNotIn('physical_condition', ['Rusak', 'Rusak Ringan', 'Rusak Berat', 'Hilang']);
            })
            ->count();

        $this->update([
            'total_copies'     => $total,
            'available_copies' => $available,
        ]);
    }

    /**
     * Helper counts breakdown.
     */
    public function getBorrowedCopiesCountAttribute(): int
    {
        return $this->collections()->where('availability_status', 'BORROWED')->count();
    }

    public function getQuarantinedCopiesCountAttribute(): int
    {
        return $this->collections()
            ->where(fn($q) => $q->where('is_quarantine', true)->orWhere('availability_status', 'QUARANTINED'))
            ->count();
    }

    public function getDamagedCopiesCountAttribute(): int
    {
        return $this->collections()
            ->whereIn('physical_condition', ['Rusak', 'Rusak Ringan', 'Rusak Berat'])
            ->count();
    }
}
