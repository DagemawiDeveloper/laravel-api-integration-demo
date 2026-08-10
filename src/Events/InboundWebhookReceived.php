<?php

namespace Dagemawi\RelayHub\Events;

use Dagemawi\RelayHub\Models\InboundWebhook;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InboundWebhookReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly InboundWebhook $webhook)
    {
    }
}
