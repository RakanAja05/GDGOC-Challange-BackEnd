<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ConversationController extends Controller
{
    #[OA\Get(
        path: '/api/conversations',
        summary: 'List conversations',
        tags: ['Conversations'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['open', 'pending', 'closed'])),
            new OA\Parameter(name: 'priority', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['low', 'medium', 'high'])),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Conversation list'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:open,pending,closed'],
            'priority' => ['nullable', 'in:low,medium,high'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Conversation::query()
            ->with('customer')
            ->withCount('messages');

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['priority'])) {
            $query->where('priority', $validated['priority']);
        }

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->whereHas('customer', function ($customerQuery) use ($search) {
                $customerQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $perPage = $validated['per_page'] ?? 20;

        $conversations = $query
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json($conversations);
    }

    #[OA\Get(
        path: '/api/conversations/inbox',
        summary: 'Inbox conversations sorted by priority and last message time',
        tags: ['Conversations'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Inbox list'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function inbox(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $validated['per_page'] ?? 20;

        $conversations = Conversation::query()
            ->select([
                'id',
                'customer_id',
                'status',
                'priority',
                'last_message_from',
                'last_message_at',
                'created_at',
                'updated_at',
            ])
            ->with([
                'customer:id,name',
                'aiInsight:id,conversation_id,sentiment,issue_category',
            ])
            ->orderByRaw("FIELD(priority, 'high', 'medium', 'low')")
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(function (Conversation $conversation) {
                return [
                    'id' => $conversation->id,
                    'status' => $conversation->status,
                    'priority' => $conversation->priority,
                    'last_message_from' => $conversation->last_message_from,
                    'last_message_at' => $conversation->last_message_at,
                    'customer_name' => $conversation->customer?->name,
                    'sentiment' => $conversation->aiInsight?->sentiment,
                    'issue_category' => $conversation->aiInsight?->issue_category,
                ];
            });

        return response()->json($conversations);
    }

    #[OA\Get(
        path: '/api/conversations/{conversation}',
        summary: 'Get conversation detail',
        tags: ['Conversations'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'conversation', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'before_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Conversation detail'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $validated = $request->validate([
            'before_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $conversation->load([
            'customer',
            'aiInsight',
        ]);

        $messagesQuery = $conversation->messages()
            ->with('sender')
            ->orderByDesc('id');

        if (! empty($validated['before_id'])) {
            $messagesQuery->where('id', '<', $validated['before_id']);
        }

        $messages = $messagesQuery
            ->limit(10)
            ->get()
            ->reverse()
            ->values();

        $nextBeforeId = $messages->isNotEmpty() ? $messages->first()->id : null;
        $hasMore = $nextBeforeId
            ? $conversation->messages()->where('id', '<', $nextBeforeId)->exists()
            : false;

        return response()->json([
            'conversation' => $conversation,
            'messages' => $messages,
            'pagination' => [
                'per_page' => 10,
                'next_before_id' => $nextBeforeId,
                'has_more' => $hasMore,
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/conversations/{conversation}/messages',
        summary: 'Send agent reply',
        tags: ['Conversations'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'conversation', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['content'],
                properties: [
                    new OA\Property(property: 'content', type: 'string', example: 'We are looking into your issue.'),
                    new OA\Property(property: 'sender_id', type: 'integer', nullable: true, example: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Updated conversation'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function sendReply(Request $request, Conversation $conversation): JsonResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string'],
            'sender_id' => ['nullable', 'exists:users,id'],
        ]);

        $senderId = $request->user()?->id ?? $validated['sender_id'] ?? null;

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'agent',
            'sender_id' => $senderId,
            'content' => $validated['content'],
            'created_at' => now(),
        ]);

        $conversation->forceFill([
            'last_message_from' => 'agent',
            'last_message_at' => now(),
            'status' => 'pending',
        ])->save();

        $conversation->load([
            'customer',
            'aiInsight',
            'messages' => function ($query) {
                $query->with('sender')->orderBy('created_at');
            },
        ]);

        return response()->json($conversation, 201);
    }
}
