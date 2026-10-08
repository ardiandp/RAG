@extends('dashboard.layouts.app')

@section('title', 'Agent Runs')

@section('content')
    <h1>Agent Runs</h1>

    <table>
        <thead>
            <tr>
                <th>Run</th>
                <th>Status</th>
                <th>Input</th>
                <th>Langkah</th>
                <th>User</th>
                <th>Durasi</th>
                <th>Waktu</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($runs as $run)
                <tr>
                    <td><a href="/dashboard/runs/{{ $run->id }}">#{{ $run->id }}</a></td>
                    <td><span class="badge badge-{{ $run->status }}">{{ $run->status }}</span></td>
                    <td class="truncate">{{ $run->input }}</td>
                    <td>
                        {{ $run->steps }}
                        @if ($run->toolCalls->isNotEmpty())
                            <details class="inline">
                                <summary>lihat {{ $run->toolCalls->count() }} call</summary>
                                <ul class="toolcall-list">
                                    @foreach ($run->toolCalls as $call)
                                        <li>
                                            <span class="badge badge-{{ $call->status }}">{{ $call->status }}</span>
                                            {{ $call->tool_name }}
                                            <span class="mono">{{ json_encode($call->arguments, JSON_UNESCAPED_UNICODE) }}</span>
                                            <span class="mono">→</span>
                                            <span class="mono">{{ json_encode($call->result, JSON_UNESCAPED_UNICODE) }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                    </td>
                    <td>{{ $run->user?->email ?? '—' }}</td>
                    <td>{{ $run->finished_at ? number_format($run->started_at->diffInMilliseconds($run->finished_at)) : '—' }} ms</td>
                    <td>{{ $run->created_at->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">Belum ada agent run.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="pagination">{{ $runs->links() }}</div>
@endsection