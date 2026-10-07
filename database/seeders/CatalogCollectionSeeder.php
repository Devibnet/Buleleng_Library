<?php

namespace Database\Seeders;

use App\Models\Catalog;
use App\Models\Collection;
use Illuminate\Database\Seeder;

class CatalogCollectionSeeder extends Seeder
{
    public function run(): void
    {
        $books = [
            [
                'title'       => 'Laskar Pelangi',
                'author_main' => 'Andrea Hirata',
                'publisher'   => 'Bentang Pustaka',
                'publish_year'=> '2005',
                'isbn'        => '978-979-3062-79-2',
                'barcodes'    => ['BUK-00101', 'BUK-00102', 'BUK-00103'],
            ],
            [
                'title'       => 'Bumi Manusia',
                'author_main' => 'Pramoedya Ananta Toer',
                'publisher'   => 'Lentera Dipantara',
                'publish_year'=> '1980',
                'isbn'        => '978-979-97312-3-4',
                'barcodes'    => ['BUK-00201', 'BUK-00202'],
            ],
            [
                'title'       => 'Sejarah Kebudayaan Bali & Buleleng',
                'author_main' => 'I Gusti Ngurah Bagus',
                'publisher'   => 'Pustaka Bali',
                'publish_year'=> '2018',
                'isbn'        => '978-602-1234-56-7',
                'barcodes'    => ['BUK-00301', 'BUK-00302'],
            ],
        ];

        foreach ($books as $b) {
            $catalog = Catalog::updateOrCreate(
                ['title' => $b['title']],
                [
                    'author_main'   => $b['author_main'],
                    'publisher'     => $b['publisher'],
                    'publish_year'  => $b['publish_year'],
                    'isbn'          => $b['isbn'],
                    'total_copies'  => count($b['barcodes']),
                    'available_copies' => count($b['barcodes']),
                ]
            );

            foreach ($b['barcodes'] as $barcode) {
                Collection::updateOrCreate(
                    ['barcode_no' => $barcode],
                    [
                        'catalog_id'          => $catalog->id,
                        'availability_status' => 'AVAILABLE',
                        'currency'            => 'IDR',
                        'price'               => 75000,
                    ]
                );
            }
        }
    }
}
