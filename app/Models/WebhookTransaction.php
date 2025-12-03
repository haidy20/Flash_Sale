<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookTransaction extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'webhook_transactions';

    protected $fillable = [
        'idempotency_key',
        'order_id',
        'processed_at',
        'is_successful',
    ];
}