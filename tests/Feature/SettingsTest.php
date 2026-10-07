<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function authenticated_users_can_view_settings(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->get('/dashboard/settings')->assertOk()->assertSee('Ollama / Model');
        $this->actingAs($admin)->get('/dashboard/settings')->assertOk()->assertSee('Tool Terdaftar');
    }

    #[Test]
    public function settings_page_does_not_display_secrets(): void
    {
        config(['database.connections.pgsql.password' => 'rahasia123']);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/dashboard/settings')
            ->assertOk()
            ->assertDontSee('rahasia123')
            ->assertDontSee('password');
    }

    #[Test]
    public function tools_list_includes_registered_tools(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->get('/dashboard/settings')
            ->assertOk()
            ->assertSee('search_knowledge')
            ->assertSee('get_sales_summary');
    }
}
