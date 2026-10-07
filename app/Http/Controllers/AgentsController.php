<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Services\AuditService;
use App\Tools\ToolRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AgentsController extends Controller
{
    public function index(): View
    {
        return view('dashboard.agents.index', [
            'agents' => Agent::withCount(['conversations', 'agentRuns'])
                ->latest('id')->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('dashboard.agents.form', [
            'agent' => new Agent,
            'tools' => app(ToolRegistry::class)->all(),
        ]);
    }

    public function store(Request $request, ToolRegistry $registry, AuditService $audit): RedirectResponse
    {
        $data = $this->validated($request, $registry);

        $agent = Agent::create($data);

        $audit->log('agent.created', $request->user(), [
            'agent_id' => $agent->id,
            'name' => $agent->name,
        ], request: $request);

        return redirect('/dashboard/agents')->with('status', "Agent \"{$agent->name}\" dibuat.");
    }

    public function edit(Agent $agent): View
    {
        return view('dashboard.agents.form', [
            'agent' => $agent,
            'tools' => app(ToolRegistry::class)->all(),
        ]);
    }

    public function update(Request $request, Agent $agent, ToolRegistry $registry, AuditService $audit): RedirectResponse
    {
        $data = $this->validated($request, $registry);

        $agent->update($data);

        $audit->log('agent.updated', $request->user(), [
            'agent_id' => $agent->id,
            'name' => $agent->name,
        ], request: $request);

        return redirect('/dashboard/agents')->with('status', "Agent \"{$agent->name}\" diperbarui.");
    }

    public function toggle(Agent $agent, AuditService $audit): RedirectResponse
    {
        $agent->update(['is_active' => !$agent->is_active]);

        $audit->log('agent.toggled', null, [
            'agent_id' => $agent->id,
            'is_active' => !$agent->is_active,
        ]);

        return redirect('/dashboard/agents')
            ->with('status', "Agent \"{$agent->name}\" di-".(!$agent->is_active ? 'nonaktifkan' : 'aktifkan').'.');
    }

    public function destroy(Agent $agent, AuditService $audit): RedirectResponse
    {
        $name = $agent->name;
        $agent->delete();

        $audit->log('agent.deleted', null, ['name' => $name]);

        return redirect('/dashboard/agents')->with('status', "Agent \"{$name}\" dihapus.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ToolRegistry $registry): array
    {
        $names = array_keys($registry->all());

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'system_prompt' => ['nullable', 'string'],
            'model' => ['nullable', 'string', 'max:255'],
            'max_steps' => ['required', 'integer', 'min:1', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
            'tools' => ['nullable', 'array'],
            'tools.*' => [Rule::in($names)],
        ]);

        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'system_prompt' => $data['system_prompt'] ?? null,
            'model' => $data['model'] ?? null,
            'max_steps' => (int) $data['max_steps'],
            'is_active' => $request->boolean('is_active'),
            'tools' => array_values($data['tools'] ?? []),
        ];
    }
}
