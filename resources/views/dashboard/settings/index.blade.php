@extends('dashboard.layouts.app')

@section('title', 'Settings')

@section('content')
    <h1>Settings</h1>

    <section class="grid">
        <div class="card">
            <h3>Aplikasi</h3>
            <ul class="kv">
                @foreach ($aps as $key => $value)
                    <li><span>{{ $key }}</span><code>{{ $value }}</code></li>
                @endforeach
            </ul>
        </div>

        <div class="card">
            <h3>Ollama / Model</h3>
            <ul class="kv">
                @foreach ($ollama as $key => $value)
                    <li><span>{{ $key }}</span><code>{{ $value }}</code></li>
                @endforeach
            </ul>
        </div>

        <div class="card">
            <h3>Agent</h3>
            <ul class="kv">
                @foreach ($agent as $key => $value)
                    <li><span>{{ $key }}</span><code>{{ $value }}</code></li>
                @endforeach
            </ul>
        </div>
    </section>

    <h2>Tool Terdaftar</h2>
    <table>
        <thead>
            <tr>
                <th>Nama</th>
                <th>Deskripsi</th>
                <th>Permission</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($tools as $tool)
                <tr>
                    <td><code>{{ $tool->name() }}</code></td>
                    <td>{{ $tool->description() }}</td>
                    <td><code>{{ $tool->permission() ?? 'none' }}</code></td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection