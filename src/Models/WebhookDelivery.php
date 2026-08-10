<?php

namespace Dagemawi\RelayHub\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookDelivery extends Model
{
    protected $table = 'relayhub_webhook_deliveries';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'response_body' => 'array',
        'delivered_at' => 'datetime',
    ];
}
