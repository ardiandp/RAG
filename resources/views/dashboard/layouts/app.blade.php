<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') · IRNIS AI</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <header class="topbar">
        <a class="brand" href="/dashboard">IRNIS <span>AI</span></a>
        <nav>
            <a href="/dashboard/chat">Chat</a>
            <a href="/dashboard/conversations">Conversations</a>
            <a href="/dashboard/runs">Agent Runs</a>
            <a href="/dashboard/agents">Agents</a>
            <a href="/dashboard/tools">Tools</a>
            <a href="/dashboard/knowledge">Knowledge</a>
            <a href="/dashboard/audit-logs">Audit Logs</a>
            <a href="/dashboard/settings">Settings</a>
        </nav>
        <div class="topbar-right">
            <span>{{ auth()->user()->email }}</span>
            <form method="POST" action="/logout">
                @csrf
                <button type="submit" class="btn btn-ghost">Keluar</button>
            </form>
        </div>
    </header>
    <main class="container">
        @if (session('status'))
            <div class="alert">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-error">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @yield('content')
    </main>
</body>
</html>