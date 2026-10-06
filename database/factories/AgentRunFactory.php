<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentRun>
 */
class AgentRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'agent_id' => Agent::factory(),
            'user_id' => User::factory(),
            'status' => 'running',
            'input' => fake()->sentence(),
            'output' => null,
            'steps' => 0,
            'max_steps' => 5,
            'error' => null,
            'started_at' => now(),
            'finished_at' => null,
        ];
    }
}
