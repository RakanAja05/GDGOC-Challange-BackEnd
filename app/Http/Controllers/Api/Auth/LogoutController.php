<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class LogoutController extends Controller
{
    #[OA\Post(
        path: '/api/logout',
        summary: 'Logout (revokes tokens)',
        tags: ['Auth'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Logged out'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            $user->tokens()->delete();
        }

        return response()->json([
            'message' => 'Logged out.',
        ]);
    }
}
