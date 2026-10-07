<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuditLogsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admins_see_all_audit_logs(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $other = User::factory()->create(['role' => 'user']);

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'agent_run.finished',
            'context' => ['run_id' => 1, 'status' => 'success'],
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
        ]);

        AuditLog::create([
            'user_id' => $other->id,
            'action' => 'tool_call.denied',
            'context' => ['tool_name' => 'search_knowledge'],
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
        ]);

        $this->actingAs($admin)->get('/dashboard/audit-logs')
            ->assertOk()
            ->assertSee('agent_run.finished')
            ->assertSee('tool_call.denied');
    }

    #[Test]
    public function regular_users_only_see_their_own_logs(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $other = User::factory()->create(['role' => 'user']);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'agent_run.started',
            'context' => ['run_id' => 1],
            'created_at' => now(),
        ]);

        AuditLog::create([
            'user_id' => $other->id,
            'action' => 'agent_run.started',
            'context' => ['run_id' => 2],
            'created_at' => now(),
        ]);

        $this->actingAs($user)->get('/dashboard/audit-logs')
            ->assertOk()
            ->assertSee('"run_id": 1')
            ->assertDontSee('"run_id": 2');
    }

    #[Test]
    public function logs_can_be_filtered_by_action_and_tool(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'knowledge.document_uploaded',
            'context' => ['document_id' => 10],
            'created_at' => now(),
        ]);
        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'knowledge.document_deleted',
            'context' => ['document_id' => 12, 'tool_name' => 'search_knowledge'],
            'created_at' => now(),
        ]);

        $this->actingAs($admin)->get('/dashboard/audit-logs?action=uploaded')
            ->assertOk()
            ->assertSee('knowledge.document_uploaded')
            ->assertDontSee('knowledge.document_deleted');

        $this->actingAs($admin)->get('/dashboard/audit-logs?tool=search_knowledge')
            ->assertOk()
            ->assertSee('knowledge.document_deleted');
    }
}
