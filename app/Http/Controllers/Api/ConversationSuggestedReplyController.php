<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * @deprecated Use POST /api/v1/ai/analyze instead.
 */
class ConversationSuggestedReplyController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => 'Deprecated. Use /api/v1/ai/analyze.',
        ], 410);
    }
}
