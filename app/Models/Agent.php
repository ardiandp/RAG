<?php

namespace App\Models;

use Database\Factories\AgentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agent extends Model
{
    /** @use HasFactory<AgentFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'system_prompt',
        'model',
        'tools',
        'is_active',
        'max_steps',
    ];

    protected function casts(): array
    {
        return [
            'tools' => 'array',
            'is_active' => 'boolean',
            'max_steps' => 'integer',
        ];
    }

    /** @return HasMany<Conversation, $this> */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /** @return HasMany<AgentRun, $this> */
    public function agentRuns(): HasMany
    {
        return $this->hasMany(AgentRun::class);
    }

    /**
     * Apakah agent diizinkan memakai tool bernama $name.
     */
    public function allowsTool(string $name): bool
    {
        return empty($this->tools) || in_array($name, $this->tools, true);
    }
}
