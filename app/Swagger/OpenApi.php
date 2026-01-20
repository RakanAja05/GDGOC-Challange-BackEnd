<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'GDGoC API',
    version: '1.0.0',
    description: 'API documentation for GDGoC Challenge'
)]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Token',
    description: 'Use your Bearer token from Laravel Sanctum'
)]
#[OA\Get(
    path: '/api/user',
    summary: 'Get authenticated user',
    tags: ['Auth'],
    security: [['sanctum' => []]],
    responses: [
        new OA\Response(response: 200, description: 'Authenticated user'),
        new OA\Response(response: 401, description: 'Unauthenticated'),
    ]
)]
class OpenApi
{
    // This class is intentionally left blank.
}
