<?php

namespace Dagemawi\RelayHub\Models;

use Illuminate\Database\Eloquent\Model;

class InboundWebhook extends Model
{
    protected $table = 'relayhub_inbound_webhooks';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];
}
