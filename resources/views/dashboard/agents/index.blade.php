@extends('dashboard.layouts.app')

@section('title', 'Agents')

@section('content')
    <div class="page-head">
        <h1>Agents</h1>
        @can('manage_dashboard')
            <a class="btn btn-primary" href="/dashboard/agents/create">+ Buat Agent</a>
        @endcan
    </div>

    <table>
        <thead>
            <tr>
                <th>Nama</th>
                <th>Status</th>
                <th>Max Steps</th>
                <th>Tools</th>
                <th>Conversations</th>
                <th>Runs</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($agents as $agent)
                <tr>
                    <td>
                        <strong>{{ $agent->name }}</strong>
                        @if ($agent->description)
                            <div class="muted">{{ str($agent->description)->limit(60) }}</div>
                        @endif
                    </td>
                    <td>
                        <span class="badge badge-{{ $agent->is_active ? 'success' : 'denied' }}">
                            {{ $agent->is_active ? 'aktif' : 'nonaktif' }}
                        </span>
                    </td>
                    <td>{{ $agent->max_steps }}</td>
                    <td class="mono">
                        {{ $agent->tools ? implode(', ', $agent->tools) : 'semua' }}
                    </td>
                    <td>{{ $agent->conversations_count }}</td>
                    <td>{{ $agent->agent_runs_count }}</td>
                    <td>
                        @can('manage_dashboard')
                            <div class="actions">
                                <a class="btn" href="/dashboard/agents/{{ $agent->id }}/edit">Edit</a>
                                <form method="POST" action="/dashboard/agents/{{ $agent->id }}/toggle" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn">{{ $agent->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                </form>
                                <form method="POST" action="/dashboard/agents/{{ $agent->id }}" class="inline"
                                      onsubmit="return confirm('Hapus agent \'{{ $agent->name }}\'?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger">Hapus</button>
                                </form>
                            </div>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">Belum ada agent terdaftar.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="pagination">{{ $agents->links() }}</div>
@endsection