@extends('dashboard.layouts.app')

@section('title', 'Dashboard')

@section('content')
    <h1>Overview</h1>

    <section class="grid">
        <div class="card"><div class="stat">Pengguna</div><div class="stat-value">{{ number_format($users) }}</div></div>
        <div class="card"><div class="stat">Conversations</div><div class="stat-value">{{ number_format($conversations) }}</div></div>
        <div class="card"><div class="stat">Pesan</div><div class="stat-value">{{ number_format($messages) }}</div></div>
        <div class="card"><div class="stat">Agent Runs</div><div class="stat-value">{{ number_format($runs) }}</div></div>
        <div class="card"><div class="stat">Runs Sukses</div><div class="stat-value stat-ok">{{ number_format($runsSuccess) }}</div></div>
        <div class="card"><div class="stat">Runs Gagal</div><div class="stat-value stat-bad">{{ number_format($runsFailed) }}</div></div>
        <div class="card"><div class="stat">Tool Calls</div><div class="stat-value">{{ number_format($toolCalls) }}</div></div>
        <div class="card"><div class="stat">Tool Calls Ditolak</div><div class="stat-value stat-bad">{{ number_format($toolCallsDenied) }}</div></div>
        <div class="card"><div class="stat">Dokumen</div><div class="stat-value">{{ number_format($documents) }}</div></div>
        <div class="card"><div class="stat">Chunk Embedding</div><div class="stat-value">{{ number_format($chunks) }}</div></div>
        <div class="card"><div class="stat">Audit Log</div><div class="stat-value">{{ number_format($audits) }}</div></div>
    </section>

    <h2>Aktivitas Terbaru</h2>
    <table>
        <thead>
            <tr>
                <th>Run</th>
                <th>Status</th>
                <th>Input</th>
                <th>Conversation</th>
                <th>User</th>
                <th>Waktu</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($latestRuns as $run)
                <tr>
                    <td>#{{ $run->id }}</td>
                    <td><span class="badge badge-{{ $run->status }}">{{ $run->status }}</span></td>
                    <td class="truncate">{{ $run->input }}</td>
                    <td>{{ $run->conversation?->title ?? '—' }}</td>
                    <td>{{ $run->user?->email ?? '—' }}</td>
                    <td>{{ $run->created_at->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">Belum ada aktivitas agent.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection