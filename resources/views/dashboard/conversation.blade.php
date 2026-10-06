@extends('dashboard.layouts.app')

@section('title', 'Conversation')

@section('content')
    <p><a href="/dashboard/conversations">← Kembali</a></p>
    <h1>{{ $conversation->title }}</h1>
    <p class="muted">
        {{ $conversation->user?->email ?? 'Tanpa user' }} ·
        {{ $conversation->messages()->count() }} pesan ·
        dibuat {{ $conversation->created_at->diffForHumans() }}
    </p>

    <section class="transcript">
        @foreach ($conversation->messages as $message)
            <div class="bubble bubble-{{ $message->role }}">
                <div class="bubble-role">{{ $message->role }}</div>
                <div class="bubble-body">{{ $message->content }}</div>
                @if ($message->metadata)
                    <details class="meta">
                        <summary>metadata</summary>
                        <pre>{{ json_encode($message->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    </details>
                @endif
            </div>
        @endforeach
    </section>

    @if ($conversation->agentRuns->isNotEmpty())
        <h2>Agent Runs</h2>
        @foreach ($conversation->agentRuns as $run)
            <details class="run">
                <summary>
                    Run #{{ $run->id }} · <span class="badge badge-{{ $run->status }}">{{ $run->status }}</span>
                    · {{ $run->steps }} langkah
                    · {{ $run->finished_at ? number_format($run->started_at->diffInMilliseconds($run->finished_at)) : '—' }} ms
                </summary>
                <p><strong>Input:</strong> {{ $run->input }}</p>
                <p><strong>Output:</strong> {{ $run->output }}</p>
                @if ($run->error)
                    <p class="stat-bad"><strong>Error:</strong> {{ $run->error }}</p>
                @endif
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
            </details>
        @endforeach
    @endif
@endsection