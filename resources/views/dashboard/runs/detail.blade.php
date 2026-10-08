@extends('dashboard.layouts.app')

@section('title', 'Agent Run #' . $run->id)

@section('content')
    <p><a href="/dashboard/runs">← Kembali ke Agent Runs</a></p>
    <h1>Agent Run #{{ $run->id }}</h1>

    <p class="muted">
        <span class="badge badge-{{ $run->status }}">{{ $run->status }}</span> ·
        {{ $run->steps }}/{{ $run->max_steps }} langkah ·
        {{ $run->finished_at ? number_format($run->started_at->diffInMilliseconds($run->finished_at)) : '—' }} ms ·
        {{ $run->created_at->diffForHumans() }}
    </p>

    <dl>
        <dt><strong>User</strong></dt><dd>{{ $run->user?->email ?? '—' }}</dd>
        <dt><strong>Agent</strong></dt><dd>{{ $run->agent?->name ?? '—' }}</dd>
        <dt><strong>Conversation</strong></dt>
        <dd>
            @if ($run->conversation)
                <a href="/dashboard/conversations/{{ $run->conversation->id }}">{{ $run->conversation->title }}</a>
            @else
                —
            @endif
        </dd>
    </dl>

    <h2>Input</h2>
    <p>{{ $run->input }}</p>

    <h2>Alur Eksekusi</h2>
    @if ($run->toolCalls->isEmpty())
        <p class="muted">Tidak ada tool call — jawaban langsung dari model.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Step</th>
                    <th>Tool</th>
                    <th>Status</th>
                    <th>Durasi</th>
                    <th>Arguments</th>
                    <th>Hasil</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($run->toolCalls as $call)
                    <tr>
                        <td>{{ $call->step }}</td>
                        <td>{{ $call->tool_name }}</td>
                        <td><span class="badge badge-{{ $call->status }}">{{ $call->status }}</span></td>
                        <td>{{ $call->duration_ms }} ms</td>
                        <td class="mono">{{ json_encode($call->arguments, JSON_UNESCAPED_UNICODE) }}</td>
                        <td class="mono">{{ json_encode($call->result, JSON_UNESCAPED_UNICODE) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($run->error)
        <h2>Error</h2>
        <p class="stat-bad">{{ $run->error }}</p>
    @endif

    <h2>Jawaban Akhir</h2>
    <p>{{ $run->output ?? '—' }}</p>
@endsection
