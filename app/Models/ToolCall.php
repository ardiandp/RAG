<?php

namespace App\Models;

use Database\Factories\ToolCallFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ToolCall extends Model
{
    /** @use HasFactory<ToolCallFactory> */
    use HasFactory;

    protected $fillable = [
        'agent_run_id',
        'tool_id',
        'tool_name',
        'arguments',
        'result',
        'status',
        'error',
        'duration_ms',
        'step',
    ];

    protected function casts(): array
    {
        return [
            'arguments' => 'array',
            'result' => 'array',
            'duration_ms' => 'integer',
            'step' => 'integer',
        ];
    }

    /** @return BelongsTo<AgentRun, $this> */
    public function agentRun(): BelongsTo
    {
        return $this->belongsTo(AgentRun::class);
    }

    /** @return BelongsTo<Tool, $this> */
    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }
}
