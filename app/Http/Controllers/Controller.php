<?php

namespace App\Http\Controllers;

use OpenApi\Annotations as OA;
use OpenApi\Attributes as OAA;

#[OAA\Info(
    version: "1.0.0",
    title: "API System Perpustakaan Kabupaten Buleleng",
    description: "Dokumentasi RESTful API Interaktif Sistem Manajemen Perpustakaan Daerah Kabupaten Buleleng"
)]
#[OAA\Server(
    url: L5_SWAGGER_CONST_HOST,
    description: "Base API Server Perpustakaan"
)]
#[OAA\SecurityScheme(
    securityScheme: "bearerAuth",
    type: "http",
    scheme: "bearer",
    bearerFormat: "JWT",
    description: "Masukkan Token Bearer autentikasi Sanctum / JWT"
)]
/**
 * @OA\Info(
 *     title="API System Perpustakaan Kabupaten Buleleng",
 *     version="1.0.0",
 *     description="Dokumentasi RESTful API Interaktif Sistem Manajemen Perpustakaan Daerah Kabupaten Buleleng"
 * )
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="Base API Server Perpustakaan"
 * )
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 */
abstract class Controller
{
    //
}
