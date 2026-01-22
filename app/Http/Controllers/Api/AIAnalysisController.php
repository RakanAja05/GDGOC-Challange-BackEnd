<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\AIAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AIAnalysisController extends Controller
{
    #[OA\Post(
        path: '/api/v1/ai/inbox',
        summary: 'Inbox AI (issue, sentiment, priority)',
        description: 'Auto-triggered after a customer message is saved. Only users with roles agent or lead can access this endpoint.',
        tags: ['AI Insights'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['conversation_id'],
                properties: [
                    new OA\Property(property: 'conversation_id', type: 'integer', example: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Inbox AI result',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'conversation_id', type: 'integer', example: 1),
                                new OA\Property(property: 'issue_category', type: 'string', example: 'login issue'),
                                new OA\Property(property: 'sentiment', type: 'string', example: 'negative'),
                                new OA\Property(property: 'sentiment_score', type: 'number', example: 0.12),
                                new OA\Property(property: 'priority', type: 'string', enum: ['low', 'medium', 'high'], example: 'high'),
                            ]
                        ),
                        new OA\Property(
                            property: 'meta',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'issue', type: 'object', additionalProperties: true),
                                new OA\Property(property: 'sentiment', type: 'object', additionalProperties: true),
                                new OA\Property(property: 'priority', type: 'object', additionalProperties: true),
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
    public function inbox(Request $request, AIAnalysisService $analysisService): JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => ['required', 'integer', 'exists:conversations,id'],
        ]);

        $conversation = Conversation::findOrFail($validated['conversation_id']);

        $result = $analysisService->analyzeInbox($conversation);

        return response()->json([
            'status' => 'success',
            'data' => [
                'conversation_id' => $conversation->id,
                'issue_category' => $result['data']['issue_category'],
                'sentiment' => $result['data']['sentiment'],
                'sentiment_score' => $result['data']['sentiment_score'],
                'priority' => $result['data']['priority'],
            ],
            'meta' => $result['meta'],
        ]);
    }

    #[OA\Post(
        path: '/api/v1/ai/summary',
        summary: 'Conversation summary (on demand)',
        description: 'Generates summary once and caches it until a new message arrives.',
        tags: ['AI Insights'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['conversation_id'],
                properties: [
                    new OA\Property(property: 'conversation_id', type: 'integer', example: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Summary result',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'conversation_id', type: 'integer', example: 1),
                                new OA\Property(property: 'summary', type: 'string', example: 'Customer cannot login.'),
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
    public function summary(Request $request, AIAnalysisService $analysisService): JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => ['required', 'integer', 'exists:conversations,id'],
        ]);

        $conversation = Conversation::findOrFail($validated['conversation_id']);
        $result = $analysisService->getSummary($conversation);

        return response()->json([
            'status' => 'success',
            'data' => [
                'conversation_id' => $conversation->id,
                'summary' => $result['data']['summary'] ?? null,
            ],
            'meta' => [
                'cached' => $result['cached'],
                'fallback' => $result['fallback'],
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/v1/ai/reply',
        summary: 'Suggested reply (manual trigger)',
        description: 'Generates a fresh reply draft; no caching and no persistence.',
        tags: ['AI Insights'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['conversation_id'],
                properties: [
                    new OA\Property(property: 'conversation_id', type: 'integer', example: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Reply suggestion result',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'conversation_id', type: 'integer', example: 1),
                                new OA\Property(property: 'reply', type: 'string', example: 'Hi, we are checking your issue.'),
                            ]
                        ),
                        new OA\Property(
                            property: 'meta',
                            type: 'object',
                            properties: [
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
    public function reply(Request $request, AIAnalysisService $analysisService): JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => ['required', 'integer', 'exists:conversations,id'],
        ]);

        $conversation = Conversation::findOrFail($validated['conversation_id']);
        $result = $analysisService->suggestReply($conversation);

        return response()->json([
            'status' => 'success',
            'data' => [
                'conversation_id' => $conversation->id,
                'reply' => $result['data']['reply'] ?? null,
            ],
            'meta' => [
                'fallback' => $result['fallback'],
            ],
        ]);
    }
}
