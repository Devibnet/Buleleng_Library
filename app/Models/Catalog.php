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
}
