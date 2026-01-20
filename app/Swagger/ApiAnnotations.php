<?php

use OpenApi\Annotations as OA;

/**
 * @OA\OpenApi(
 *     @OA\Info(
 *         title="GDGoC API",
 *         version="1.0.0",
 *         description="API documentation for GDGoC Challenge"
 *     ),
 *     @OA\PathItem(
 *         path="/api/user",
 *         @OA\Get(
 *             summary="Get authenticated user",
 *             tags={"Auth"},
 *             @OA\Response(
 *                 response=200,
 *                 description="Authenticated user"
 *             ),
 *             @OA\Response(
 *                 response=401,
 *                 description="Unauthenticated"
 *             )
 *         )
 *     )
 * )
 */
