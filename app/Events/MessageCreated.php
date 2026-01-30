<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Message $message,
    ) {
    }
}
