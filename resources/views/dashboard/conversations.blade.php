@extends('dashboard.layouts.app')

@section('title', 'Conversations')

@section('content')
    <h1>Conversations</h1>

    <table>
        <thead>
            <tr>
                <th>Judul</th>
                <th>User</th>
                <th>Agent</th>
                <th>Pesan</th>
                <th>Runs</th>
                <th>Terakhir aktif</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($conversations as $conversation)
                <tr>
                    <td>{{ $conversation->title }}</td>
                    <td>{{ $conversation->user?->email ?? '—' }}</td>
                    <td>{{ $conversation->agent?->name ?? '—' }}</td>
                    <td>{{ $conversation->messages_count }}</td>
                    <td>{{ $conversation->agent_runs_count }}</td>
                    <td>{{ $conversation->updated_at->diffForHumans() }}</td>
                    <td><a class="btn" href="/dashboard/conversations/{{ $conversation->id }}">Lihat</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">Belum ada conversation.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="pagination">{{ $conversations->links() }}</div>
@endsection