<?php

namespace App\Http\Controllers\Api;

use App\Enums\AiAnalysisType;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\AIAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AIAnalysisController extends Controller
{
    #[OA\Post(
        path: '/api/v1/ai/analyze',
        summary: 'Analyze conversation with AI',
        description: 'Only users with roles agent or lead can access this endpoint.',
        tags: ['AI Insights'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['conversation_id', 'type'],
                properties: [
                    new OA\Property(property: 'conversation_id', type: 'integer', example: 1),
                    new OA\Property(
                        property: 'type',
                        type: 'string',
                        enum: ['sentiment', 'summary', 'issue', 'reply', 'priority'],
                        example: 'sentiment'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'AI analysis result',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'conversation_id', type: 'integer', example: 1),
                                new OA\Property(
                                    property: 'type',
                                    type: 'string',
                                    enum: ['sentiment', 'summary', 'issue', 'reply', 'priority'],
                                    example: 'sentiment'
                                ),
                                new OA\Property(
                                    property: 'result',
                                    type: 'object',
                                    additionalProperties: true
                                ),
                            ]
                        ),
                        new OA\Property(
                            property: 'meta',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'cached', type: 'boolean', example: false),
                                new OA\Property(property: 'fallback', type: 'boolean', example: false),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden (role: agent or lead)'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function analyze(Request $request, AIAnalysisService $analysisService): JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => ['required', 'integer', 'exists:conversations,id'],
            'type' => ['required', 'string', 'max:50'],
        ]);

        $typeInput = strtolower(trim($validated['type']));
        $typeInput = match ($typeInput) {
            'issue_classification' => 'issue',
            'suggested_reply' => 'reply',
            default => $typeInput,
        };

        try {
            $type = AiAnalysisType::from($typeInput);
        } catch (\ValueError) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid analysis type.',
            ], 422);
        }

        $conversation = Conversation::findOrFail($validated['conversation_id']);
        $result = $analysisService->analyze($conversation, $type);

        return response()->json([
            'status' => 'success',
            'data' => [
                'conversation_id' => $conversation->id,
                'type' => $type->value,
                'result' => $result['data'],
            ],
            'meta' => [
                'cached' => $result['cached'],
                'fallback' => $result['fallback'],
            ],
        ]);
    }
}
