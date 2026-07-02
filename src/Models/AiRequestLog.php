<?php

namespace Tobiebenezer\Ai\Models;

use Illuminate\Database\Eloquent\Model;

class AiRequestLog extends Model
{
    public $timestamps = false;

    protected $table = 'ai_request_logs';

    protected $fillable = [
        'user_id',
        'profile',
        'model',
        'prompt',
        'response_content',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'latency_ms',
        'created_at',
    ];

    protected $dates = [
        'created_at',
    ];

    public function toolCalls()
    {
        return $this->hasMany(AiToolCallLog::class, 'request_log_id');
    }
}
