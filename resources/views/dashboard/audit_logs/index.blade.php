@extends('dashboard.layouts.app')

@section('title', 'Audit Logs')

@section('content')
    <div class="page-head">
        <h1>Audit Logs</h1>
    </div>

    <form method="GET" action="/dashboard/audit-logs" class="card filters">
        <input type="text" name="action" value="{{ request('action') }}" placeholder="Filter aksi (mis. agent_run.finished)">
        <input type="text" name="tool" value="{{ request('tool') }}" placeholder="Filter tool (mis. search_knowledge)">
        <button type="submit" class="btn">Terapkan</button>
        @if (request('action') || request('tool'))
            <a class="btn" href="/dashboard/audit-logs">Reset</a>
        @endif
    </form>

    <table>
        <thead>
            <tr>
                <th>Waktu</th>
                <th>Aksi</th>
                <th>User</th>
                <th>IP</th>
                <th>Konteks</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td>{{ $log->created_at->diffForHumans() }}</td>
                    <td><code>{{ $log->action }}</code></td>
                    <td>{{ $log->user?->email ?? '—' }}</td>
                    <td class="mono">{{ $log->ip ?? '—' }}</td>
                    <td>
                        @if ($log->context)
                            <details class="meta">
                                <summary>lihat</summary>
                                <pre>{{ json_encode($log->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </details>
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">Tidak ada audit log.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="pagination">{{ $logs->links() }}</div>
@endsection