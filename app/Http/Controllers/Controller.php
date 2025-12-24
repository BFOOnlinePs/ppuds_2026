<?php

namespace App\Http\Controllers;

/**
 *
 * @OA\PathItem (
 *     path="/api",
 * )
 *
 * @OA\Info(
 * title="Be Found Online APis",
 * version="1.0.0",
 * ),
 *
 * @OA\SecurityScheme(
 * *   securityScheme="sanctum",
 * *   type="http",
 * *   scheme="bearer",
 * *   bearerFormat="JWT"
 * * ),
 *
 *
 */
abstract class Controller
{
    //
}
