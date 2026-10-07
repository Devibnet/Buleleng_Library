<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: "Laporan Executif",
    description: "API Engine Laporan Eksekutif Perpustakaan (Sirkulasi, Member, Katalog, Baca di Tempat, Buku Tamu) dengan ekspor PDF & Excel"
)]
class ReportController extends Controller
{
    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    #[OA\Get(
        path: "/admin/reports/circulation",
        summary: "Laporan Sirkulasi & Kinerja Operator",
        description: "Menampilkan agregasi total transaksi peminjaman, pengembalian, overdue, denda, top buku, top anggota, dan kinerja staf operator.",
        operationId: "getCirculationReport",
        tags: ["Laporan Executif"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "start_date", in: "query", description: "Tanggal awal (YYYY-MM-DD)", required: false, schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "end_date", in: "query", description: "Tanggal akhir (YYYY-MM-DD)", required: false, schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "export_type", in: "query", description: "Tipe ekspor response (view/json, pdf, excel)", required: false, schema: new OA\Schema(type: "string", enum: ["view", "pdf", "excel"], default: "view"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Laporan sirkulasi berhasil diambil / diunduh",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Data laporan berhasil diambil."),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            )
        ]
    )]
    public function circulation(Request $request)
    {
        $startDate = $request->query('start_date');
        $endDate   = $request->query('end_date');
        $exportType = strtolower($request->query('export_type', 'view'));

        $data = $this->reportService->getCirculationReport($startDate, $endDate);
        $data['title'] = 'Laporan Sirkulasi & Kinerja Operator';

        return $this->formatExportResponse($request, 'reports.circulation', $data, 'Laporan_Sirkulasi', $exportType);
    }

    #[OA\Get(
        path: "/admin/reports/members",
        summary: "Laporan Penambahan Anggota Perpustakaan",
        description: "Menampilkan total penambahan anggota baru dan breakdown statistik berdasarkan kategori pekerjaan.",
        operationId: "getMemberReport",
        tags: ["Laporan Executif"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "start_date", in: "query", description: "Tanggal awal (YYYY-MM-DD)", required: false, schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "end_date", in: "query", description: "Tanggal akhir (YYYY-MM-DD)", required: false, schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "export_type", in: "query", description: "Tipe ekspor response (view/json, pdf, excel)", required: false, schema: new OA\Schema(type: "string", enum: ["view", "pdf", "excel"], default: "view"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Laporan penambahan anggota berhasil diambil / diunduh",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Data laporan berhasil diambil."),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            )
        ]
    )]
    public function members(Request $request)
    {
        $startDate = $request->query('start_date');
        $endDate   = $request->query('end_date');
        $exportType = strtolower($request->query('export_type', 'view'));

        $data = $this->reportService->getMemberReport($startDate, $endDate);
        $data['title'] = 'Laporan Penambahan Anggota Perpustakaan';

        return $this->formatExportResponse($request, 'reports.members', $data, 'Laporan_Anggota', $exportType);
    }

    #[OA\Get(
        path: "/admin/reports/catalogs",
        summary: "Laporan Katalog & Koleksi Perpustakaan",
        description: "Menampilkan statistik judul katalog, jumlah eksemplar, ketersediaan fisik, serta kondisi karantina.",
        operationId: "getCatalogReport",
        tags: ["Laporan Executif"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "start_date", in: "query", description: "Tanggal awal (YYYY-MM-DD)", required: false, schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "end_date", in: "query", description: "Tanggal akhir (YYYY-MM-DD)", required: false, schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "export_type", in: "query", description: "Tipe ekspor response (view/json, pdf, excel)", required: false, schema: new OA\Schema(type: "string", enum: ["view", "pdf", "excel"], default: "view"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Laporan katalog dan koleksi berhasil diambil / diunduh",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Data laporan berhasil diambil."),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            )
        ]
    )]
    public function catalogs(Request $request)
    {
        $startDate = $request->query('start_date');
        $endDate   = $request->query('end_date');
        $exportType = strtolower($request->query('export_type', 'view'));

        $data = $this->reportService->getCatalogReport($startDate, $endDate);
        $data['title'] = 'Laporan Katalog & Koleksi Perpustakaan';

        return $this->formatExportResponse($request, 'reports.catalogs', $data, 'Laporan_Katalog_Koleksi', $exportType);
    }

    #[OA\Get(
        path: "/admin/reports/onsite-readings",
        summary: "Laporan Aktivitas Baca di Tempat (Onsite)",
        description: "Menampilkan total aktivitas baca di tempat, rasio anggota vs non-anggota, dan Top 10 koleksi paling sering dibaca di tempat.",
        operationId: "getOnsiteReadingReport",
        tags: ["Laporan Executif"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "start_date", in: "query", description: "Tanggal awal (YYYY-MM-DD)", required: false, schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "end_date", in: "query", description: "Tanggal akhir (YYYY-MM-DD)", required: false, schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "export_type", in: "query", description: "Tipe ekspor response (view/json, pdf, excel)", required: false, schema: new OA\Schema(type: "string", enum: ["view", "pdf", "excel"], default: "view"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Laporan baca di tempat berhasil diambil / diunduh",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Data laporan berhasil diambil."),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            )
        ]
    )]
    public function onsiteReadings(Request $request)
    {
        $startDate = $request->query('start_date');
        $endDate   = $request->query('end_date');
        $exportType = strtolower($request->query('export_type', 'view'));

        $data = $this->reportService->getOnsiteReadingReport($startDate, $endDate);
        $data['title'] = 'Laporan Aktivitas Baca di Tempat (Onsite)';

        return $this->formatExportResponse($request, 'reports.onsite_readings', $data, 'Laporan_Baca_Di_Tempat', $exportType);
    }

    #[OA\Get(
        path: "/admin/reports/guestbooks",
        summary: "Laporan Buku Tamu & Kunjungan Perpustakaan",
        description: "Menampilkan total pengunjung, demografi gender (laki-laki/perempuan), rincian tipe kunjungan, serta agregasi demografi pekerjaan dan pendidikan.",
        operationId: "getGuestbookReport",
        tags: ["Laporan Executif"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "start_date", in: "query", description: "Tanggal awal (YYYY-MM-DD)", required: false, schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "end_date", in: "query", description: "Tanggal akhir (YYYY-MM-DD)", required: false, schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "export_type", in: "query", description: "Tipe ekspor response (view/json, pdf, excel)", required: false, schema: new OA\Schema(type: "string", enum: ["view", "pdf", "excel"], default: "view"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Laporan buku tamu berhasil diambil / diunduh",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Data laporan berhasil diambil."),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            )
        ]
    )]
    public function guestbooks(Request $request)
    {
        $startDate = $request->query('start_date');
        $endDate   = $request->query('end_date');
        $exportType = strtolower($request->query('export_type', 'view'));

        $data = $this->reportService->getGuestbookReport($startDate, $endDate);
        $data['title'] = 'Laporan Buku Tamu & Kunjungan Perpustakaan';

        return $this->formatExportResponse($request, 'reports.guestbooks', $data, 'Laporan_Buku_Tamu', $exportType);
    }

    /**
     * Helper to render JSON, PDF, Excel, or HTML view based on export_type
     */
    protected function formatExportResponse(Request $request, string $viewName, array $data, string $filenamePrefix, string $exportType)
    {
        $dateSuffix = now()->format('Ymd_His');
        $filename = "{$filenamePrefix}_{$dateSuffix}";

        if ($exportType === 'pdf') {
            if (class_exists(Pdf::class)) {
                $pdf = Pdf::loadView($viewName, $data)->setPaper('a4', 'portrait');
                return $pdf->download("{$filename}.pdf");
            }
            return response()->view($viewName, $data);
        }

        if ($exportType === 'excel') {
            $html = view($viewName, $data)->render();
            return response($html, 200, [
                'Content-Type'        => 'application/vnd.ms-excel',
                'Content-Disposition' => "attachment; filename=\"{$filename}.xls\"",
                'Cache-Control'       => 'max-age=0',
            ]);
        }

        if ($request->wantsJson() || $request->query('format') === 'json') {
            return response()->json([
                'success' => true,
                'message' => 'Data laporan berhasil diambil.',
                'data'    => $data,
            ], 200);
        }

        return response()->view($viewName, $data);
    }
}
