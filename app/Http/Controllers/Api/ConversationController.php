<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ConversationController extends Controller
{
    private function customerUserId(?User $user): ?int
    {
        if (! $user || $user->role !== 'customer') {
            return null;
        }

        return $user->id;
    }

    private function applySort(\Illuminate\Database\Eloquent\Builder $query, string $sortBy, string $sortDir): void
    {
        $sortDir = strtolower($sortDir) === 'asc' ? 'asc' : 'desc';

        switch ($sortBy) {
            case 'urgency':
                // High priority first, then by last activity.
                $query->orderByRaw("FIELD(priority, 'high', 'medium', 'low')")
                    ->orderBy('last_message_at', $sortDir)
                    ->orderBy('id', $sortDir);
                break;

            case 'priority':
                $query->orderByRaw("FIELD(priority, 'high', 'medium', 'low')");
                $query->orderBy('last_message_at', 'desc')->orderByDesc('id');
                break;

            case 'oldest':
                $query->orderBy('last_message_at', 'asc')->orderBy('id', 'asc');
                break;

            case 'newest':
            default:
                $query->orderBy('last_message_at', 'desc')->orderBy('id', 'desc');
                break;
        }
    }

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
            new OA\Parameter(name: 'sort_by', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['newest', 'oldest', 'urgency', 'priority'])),
            new OA\Parameter(name: 'sort_dir', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'])),
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
            'sort_by' => ['nullable', 'in:newest,oldest,urgency,priority'],
            'sort_dir' => ['nullable', 'in:asc,desc'],
        ]);

        $query = Conversation::query()
            ->with('user')
            ->withCount('messages');

        $user = $request->user();
        $customerUserId = $this->customerUserId($user);
        if ($customerUserId !== null) {
            $query->where('user_id', $customerUserId);
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['priority'])) {
            $query->where('priority', $validated['priority']);
        }

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->whereHas('user', function ($userQuery) use ($search) {
                $userQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $perPage = $validated['per_page'] ?? 20;

        $sortBy = $validated['sort_by'] ?? 'newest';
        $sortDir = $validated['sort_dir'] ?? 'desc';
        $this->applySort($query, $sortBy, $sortDir);

        $conversations = $query
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
            new OA\Parameter(name: 'sort_by', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['urgency', 'newest', 'oldest', 'priority'])),
            new OA\Parameter(name: 'sort_dir', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'])),
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
            'sort_by' => ['nullable', 'in:urgency,newest,oldest,priority'],
            'sort_dir' => ['nullable', 'in:asc,desc'],
        ]);

        $perPage = $validated['per_page'] ?? 20;

        $user = $request->user();
        $customerUserId = $this->customerUserId($user);

        $query = Conversation::query()
            ->select([
                'id',
                'user_id',
                'status',
                'priority',
                'sentiment',
                'sentiment_score',
                'issue_category',
                'last_message_from',
                'last_message_at',
                'created_at',
                'updated_at',
            ])
            ->with([
                'user:id,name',
            ]);

        if ($customerUserId !== null) {
            $query->where('user_id', $customerUserId);
        }

        $sortBy = $validated['sort_by'] ?? 'urgency';
        $sortDir = $validated['sort_dir'] ?? 'desc';
        $this->applySort($query, $sortBy, $sortDir);

        $conversations = $query
            ->paginate($perPage)
            ->through(function (Conversation $conversation) {
                return [
                    'id' => $conversation->id,
                    'status' => $conversation->status,
                    'priority' => $conversation->priority,
                    'last_message_from' => $conversation->last_message_from,
                    'last_message_at' => $conversation->last_message_at,
                    'customer_name' => $conversation->user?->name,
                    'sentiment' => $conversation->sentiment,
                    'sentiment_score' => $conversation->sentiment_score,
                    'issue_category' => $conversation->issue_category,
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

        $user = $request->user();
        $customerUserId = $this->customerUserId($user);
        if ($customerUserId !== null && (int) $conversation->user_id !== (int) $customerUserId) {
            // Avoid leaking existence of other customers' conversations.
            return response()->json(['message' => 'Not found.'], 404);
        }

        $conversation->load([
            'user',
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
        summary: 'Send a message to a conversation',
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
        ]);

        $user = $request->user();
        $customerUserId = $this->customerUserId($user);
        if ($customerUserId !== null && (int) $conversation->user_id !== (int) $customerUserId) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $senderType = ($user && $user->role === 'customer') ? 'customer' : 'agent';
        $senderId = $senderType === 'agent' ? ($user?->id) : null;

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_type' => $senderType,
            'sender_id' => $senderId,
            'content' => $validated['content'],
            'created_at' => now(),
        ]);

        $conversation->forceFill([
            'last_message_from' => $senderType,
            'last_message_at' => now(),
            'status' => $senderType === 'customer' ? 'open' : 'pending',
        ])->save();

        $conversation->load([
            'user',
            'aiInsight',
            'messages' => function ($query) {
                $query->with('sender')->orderBy('created_at');
            },
        ]);

        return response()->json($conversation, 201);
    }
}
