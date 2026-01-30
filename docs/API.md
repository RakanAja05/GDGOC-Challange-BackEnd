# API Documentation

Base URL
- Local: `http://localhost` (prefix all endpoints with `/api`)

Authentication
- Uses Laravel Sanctum bearer tokens.
- Add header: `Authorization: Bearer <token>`

Roles
- `user`: can create conversations and send messages
- `agent`, `lead`: can access AI endpoints

Common Error Responses
- 401: `{ "message": "Unauthenticated." }`
- 403: `{ "message": "Forbidden." }` (role restriction)
- 404: `{ "message": "Not found." }`
- 422: validation errors (Laravel default format)

---

## Auth

### POST /api/register
Register a user and send OTP verification code.

Request
```json
{
  "name": "Jane Doe",
  "email": "jane@example.com",
  "password": "password",
  "password_confirmation": "password"
}
```

Response 201
```json
{
  "message": "Registered. Verification code sent.",
  "user": {
    "id": 1,
    "name": "Jane Doe",
    "email": "jane@example.com",
    "role": "user",
    "email_verified_at": null,
    "created_at": "2026-01-30T10:00:00.000000Z",
    "updated_at": "2026-01-30T10:00:00.000000Z"
  }
}
```

### POST /api/login
Login and receive token. Login is blocked until email is verified.

Request
```json
{
  "email": "jane@example.com",
  "password": "password"
}
```

Response 200
```json
{
  "token": "<sanctum_token>",
  "token_type": "Bearer",
  "user": {
    "id": 1,
    "name": "Jane Doe",
    "email": "jane@example.com",
    "role": "user",
    "email_verified_at": "2026-01-30T10:05:00.000000Z",
    "created_at": "2026-01-30T10:00:00.000000Z",
    "updated_at": "2026-01-30T10:05:00.000000Z"
  }
}
```

Response 409 (not verified)
```json
{
  "message": "Email not verified.",
  "verified": false
}
```

### POST /api/logout
Revoke all tokens for the current user.

Auth: Bearer token required.

Response 200
```json
{
  "message": "Logged out."
}
```

### GET /api/user
Return the current authenticated user.

Auth: Bearer token required.

Response 200
```json
{
  "id": 1,
  "name": "Jane Doe",
  "email": "jane@example.com",
  "role": "user",
  "email_verified_at": "2026-01-30T10:05:00.000000Z",
  "created_at": "2026-01-30T10:00:00.000000Z",
  "updated_at": "2026-01-30T10:05:00.000000Z"
}
```

---

## Email Verification (OTP)

### POST /api/email/verification/request
Request OTP. Always returns success to prevent user enumeration.

Request
```json
{
  "email": "jane@example.com"
}
```

Response 200
```json
{
  "message": "Verification code sent."
}
```

### POST /api/email/verification/verify
Verify OTP code.

Request
```json
{
  "email": "jane@example.com",
  "code": "123456"
}
```

Response 200
```json
{
  "message": "Email verified.",
  "user_id": 1
}
```

### GET /api/email/verification/status?email=...
Check verification status.

Response 200 (verified)
```json
{
  "status": "verified",
  "verified": true,
  "email_verified_at": "2026-01-30T10:05:00.000000Z"
}
```

Response 200 (unverified)
```json
{
  "status": "unverified",
  "verified": false
}
```

Response 200 (email not found)
```json
{
  "status": "none",
  "verified": false
}
```

### POST /api/email/verification/code
Dev-only: get latest OTP (only in local/testing environment).

Request
```json
{
  "email": "jane@example.com"
}
```

Response 200
```json
{
  "email": "jane@example.com",
  "code": "123456",
  "expires_at": "2026-01-30T10:10:00.000000Z"
}
```

---

## Conversations

Auth: Bearer token required and email must be verified.

### GET /api/conversations
List conversations. For users, returns only their conversations.

Query params
- `status`: `open|pending|closed`
- `priority`: `low|medium|high`
- `search`: user name/email search
- `per_page`: 1–100

Response 200 (paginated)
```json
{
  "current_page": 1,
  "data": [
    {
      "id": 1,
      "user_id": 1,
      "status": "open",
      "priority": "medium",
      "sentiment": "negative",
      "sentiment_score": "0.12",
      "issue_category": "login issue",
      "last_message_from": "user",
      "last_message_at": "2026-01-30T10:10:00.000000Z",
      "created_at": "2026-01-30T10:00:00.000000Z",
      "updated_at": "2026-01-30T10:10:00.000000Z",
      "user": {
        "id": 1,
        "name": "Jane Doe",
        "email": "jane@example.com",
        "role": "user"
      },
      "messages_count": 2
    }
  ],
  "per_page": 20,
  "total": 1
}
```

### POST /api/conversations
Create conversation with first message. Only role `user`.

Request
```json
{
  "content": "Halo, saya butuh bantuan untuk akun saya."
}
```

Response 201
```json
{
  "id": 1,
  "user_id": 1,
  "status": "open",
  "priority": "medium",
  "last_message_from": "user",
  "last_message_at": "2026-01-30T10:10:00.000000Z",
  "created_at": "2026-01-30T10:10:00.000000Z",
  "updated_at": "2026-01-30T10:10:00.000000Z",
  "user": {
    "id": 1,
    "name": "Jane Doe",
    "email": "jane@example.com",
    "role": "user"
  },
  "ai_insight": null,
  "messages": [
    {
      "id": 1,
      "conversation_id": 1,
      "sender_type": "user",
      "sender_id": 1,
      "content": "Halo, saya butuh bantuan untuk akun saya.",
      "created_at": "2026-01-30T10:10:00.000000Z",
      "sender": {
        "id": 1,
        "name": "Jane Doe",
        "email": "jane@example.com",
        "role": "user"
      }
    }
  ]
}
```

### GET /api/conversations/inbox
Inbox list sorted by priority and last message time.

Query params
- `per_page`: 1–100

Response 200 (paginated)
```json
{
  "current_page": 1,
  "data": [
    {
      "id": 1,
      "status": "open",
      "priority": "high",
      "last_message_from": "user",
      "last_message_at": "2026-01-30T10:10:00.000000Z",
      "customer_name": "Jane Doe",
      "sentiment": "negative",
      "sentiment_score": "0.12",
      "issue_category": "login issue"
    }
  ],
  "per_page": 20,
  "total": 1
}
```

### GET /api/conversations/{conversation}
Get conversation detail with messages (latest 10). Use `before_id` for pagination.

Query params
- `before_id`: load messages older than this ID

Response 200
```json
{
  "conversation": {
    "id": 1,
    "user_id": 1,
    "status": "open",
    "priority": "medium",
    "sentiment": "negative",
    "sentiment_score": "0.12",
    "issue_category": "login issue",
    "last_message_from": "user",
    "last_message_at": "2026-01-30T10:10:00.000000Z",
    "created_at": "2026-01-30T10:00:00.000000Z",
    "updated_at": "2026-01-30T10:10:00.000000Z",
    "user": {
      "id": 1,
      "name": "Jane Doe",
      "email": "jane@example.com",
      "role": "user"
    },
    "ai_insight": {
      "conversation_id": 1,
      "issue_category": "login issue",
      "sentiment": "negative",
      "sentiment_score": "0.12",
      "summary": "Customer cannot login.",
      "suggested_reply": null,
      "analyzed_at": "2026-01-30T10:12:00.000000Z"
    }
  },
  "messages": [
    {
      "id": 1,
      "conversation_id": 1,
      "sender_type": "user",
      "sender_id": 1,
      "content": "Halo, saya butuh bantuan untuk akun saya.",
      "created_at": "2026-01-30T10:10:00.000000Z",
      "sender": {
        "id": 1,
        "name": "Jane Doe",
        "email": "jane@example.com",
        "role": "user"
      }
    }
  ],
  "pagination": {
    "per_page": 10,
    "next_before_id": 1,
    "has_more": false
  }
}
```

### POST /api/conversations/{conversation}/messages
Send message to a conversation.

Request
```json
{
  "content": "Kami sedang cek masalah Anda."
}
```

Response 201
```json
{
  "id": 1,
  "user_id": 1,
  "status": "pending",
  "priority": "medium",
  "last_message_from": "agent",
  "last_message_at": "2026-01-30T10:11:00.000000Z",
  "created_at": "2026-01-30T10:10:00.000000Z",
  "updated_at": "2026-01-30T10:11:00.000000Z",
  "user": {
    "id": 1,
    "name": "Jane Doe",
    "email": "jane@example.com",
    "role": "user"
  },
  "ai_insight": null,
  "messages": [
    {
      "id": 1,
      "conversation_id": 1,
      "sender_type": "user",
      "sender_id": 1,
      "content": "Halo, saya butuh bantuan untuk akun saya.",
      "created_at": "2026-01-30T10:10:00.000000Z",
      "sender": {
        "id": 1,
        "name": "Jane Doe",
        "email": "jane@example.com",
        "role": "user"
      }
    },
    {
      "id": 2,
      "conversation_id": 1,
      "sender_type": "agent",
      "sender_id": 2,
      "content": "Kami sedang cek masalah Anda.",
      "created_at": "2026-01-30T10:11:00.000000Z",
      "sender": {
        "id": 2,
        "name": "Agent One",
        "email": "agent@example.com",
        "role": "agent"
      }
    }
  ]
}
```

---

## AI Insights (agent/lead only)

### POST /api/v1/ai/inbox
Get issue category, sentiment, priority.

Request
```json
{
  "conversation_id": 1
}
```

Response 200
```json
{
  "status": "success",
  "data": {
    "conversation_id": 1,
    "issue_category": "login issue",
    "sentiment": "negative",
    "sentiment_score": 0.12,
    "priority": "high"
  },
  "meta": {
    "issue": {},
    "sentiment": {},
    "priority": {}
  }
}
```

### POST /api/v1/ai/summary
Get summary for a conversation.

Request
```json
{
  "conversation_id": 1
}
```

Response 200
```json
{
  "status": "success",
  "data": {
    "conversation_id": 1,
    "summary": "Customer cannot login."
  },
  "meta": {
    "cached": false,
    "fallback": false
  }
}
```

### POST /api/v1/ai/reply
Get suggested reply draft.

Request
```json
{
  "conversation_id": 1
}
```

Response 200
```json
{
  "status": "success",
  "data": {
    "conversation_id": 1,
    "reply": "Hi, we are checking your issue."
  },
  "meta": {
    "fallback": false
  }
}
```
