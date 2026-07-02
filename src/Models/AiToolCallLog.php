<?php

namespace Tobiebenezer\Ai\Models;

use Illuminate\Database\Eloquent\Model;

class AiToolCallLog extends Model
{
    public $timestamps = false;

    protected $table = 'ai_tool_call_logs';

    protected $fillable = [
        'request_log_id',
        'tool_name',
        'arguments',
        'result',
        'status',
        'exception_message',
        'latency_ms',
        'created_at',
    ];

    protected $casts = [
        'arguments' => 'json',
        'result' => 'json',
    ];

    protected $dates = [
        'created_at',
    ];

    public function requestLog()
    {
        return $this->belongsTo(AiRequestLog::class, 'request_log_id');
    }
}
