<?php

namespace Database\Factories;

use App\Models\AgentRun;
use App\Models\Tool;
use App\Models\ToolCall;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ToolCall>
 */
class ToolCallFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent_run_id' => AgentRun::factory(),
            'tool_id' => Tool::factory(),
            'tool_name' => fake()->slug(2),
            'arguments' => null,
            'result' => null,
            'status' => 'pending',
            'error' => null,
            'duration_ms' => null,
            'step' => null,
        ];
    }
}
