@extends('dashboard.layouts.app')

@section('title', 'Tools')

@section('content')
    <h1>Tool Terdaftar</h1>

    <section class="grid">
        @foreach ($tools as $tool)
            <div class="card">
                <h3><code>{{ $tool->name() }}</code></h3>
                <p>{{ $tool->description() }}</p>
                <p class="muted">
                    Permission: <code>{{ $tool->permission() ?? 'none' }}</code>
                </p>
                <p class="muted">Pemakaian:</p>
                <ul class="toolcall-list">
                    @forelse (($usage[$tool->name()] ?? collect()) as $row)
                        <li>
                            <span class="badge badge-{{ $row->status }}">{{ $row->status }}</span>
                            {{ number_format($row->total) }}x
                        </li>
                    @empty
                        <li class="muted">Belum pernah dipanggil.</li>
                    @endforelse
                </ul>
                <details class="meta">
                    <summary>Schema</summary>
                    <pre>{{ json_encode($tool->schema(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </details>
            </div>
        @endforeach
    </section>
@endsection