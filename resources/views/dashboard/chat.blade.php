@extends('dashboard.layouts.app')

@section('title', 'Chat')

@section('content')
    <div class="chat-layout">
        <aside class="chat-side">
            <div class="chat-side-head">
                <h1>Chat</h1>
                <a class="btn" href="/dashboard/chat">+ Baru</a>
            </div>
            <ul class="chat-list">
                @forelse ($conversations as $item)
                    <li>
                        <a href="/dashboard/chat/{{ $item->id }}" class="{{ $conversation?->id === $item->id ? 'active' : '' }}">
                            <span class="chat-title">{{ $item->title }}</span>
                            <span class="muted">{{ $item->messages_count }} pesan · {{ $item->updated_at->diffForHumans() }}</span>
                        </a>
                    </li>
                @empty
                    <li class="muted">Belum ada percakapan.</li>
                @endforelse
            </ul>
        </aside>

        <section class="chat-main">
            @if ($conversation)
                <div class="chat-thread">
                    @foreach ($conversation->messages as $message)
                        <div class="bubble bubble-{{ $message->role }}">
                            <div class="bubble-role">{{ $message->role }}</div>
                            <div class="bubble-body">{{ $message->content }}</div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="chat-empty">
                    <p class="muted">Mulai percakapan baru dengan agent IRNIS.</p>
                    <p class="muted">Coba: "Berapa total penjualan bulan September?" atau "Berapa hari batas retur?"</p>
                </div>
            @endif

            <form id="chat-form" class="chat-input" enctype="multipart/form-data">
                <input type="text" id="chat-message" name="message" placeholder="Tulis pesan..." autocomplete="off">
                <input type="hidden" id="chat-conversation" value="{{ $conversation?->id ?? '' }}">
                <input type="file" id="chat-file" accept=".txt,.md,.markdown,.pdf,.docx" hidden>
                <label for="chat-file" class="btn" title="Lampirkan berkas">📎</label>
                <span id="chat-file-name" class="muted"></span>
                <button type="submit" class="btn btn-primary">Kirim</button>
            </form>

            <div id="chat-error" class="alert alert-error" hidden></div>
        </section>
    </div>

    <script>
        const form = document.getElementById('chat-form');
        const input = document.getElementById('chat-message');
        const fileInput = document.getElementById('chat-file');
        const fileName = document.getElementById('chat-file-name');
        const convField = document.getElementById('chat-conversation');

        fileInput.addEventListener('change', () => {
            fileName.textContent = fileInput.files.length ? fileInput.files[0].name : '';
        });
        const thread = document.querySelector('.chat-thread');
        const errorBox = document.getElementById('chat-error');
        const csrf = "{{ csrf_token() }}";

        function bubble(role, content) {
            const div = document.createElement('div');
            div.className = 'bubble bubble-' + role;
            const head = document.createElement('div');
            head.className = 'bubble-role';
            head.textContent = role;
            const body = document.createElement('div');
            body.className = 'bubble-body';
            body.textContent = content;
            div.append(head, body);
            return div;
        }

        function stepsBlock(calls) {
            if (!calls || !calls.length) return null;
            const details = document.createElement('details');
            details.className = 'bubble bubble-assistant';
            const summary = document.createElement('summary');
            summary.textContent = 'Tool calls (' + calls.length + ')';
            const list = document.createElement('ul');
            list.className = 'toolcall-list';
            calls.forEach((call) => {
                const li = document.createElement('li');
                const badge = document.createElement('span');
                badge.className = 'badge badge-' + call.status;
                badge.textContent = call.status;
                const text = document.createElement('span');
                text.textContent = ' ' + call.tool_name + ' ' + JSON.stringify(call.arguments);
                li.append(badge, text);
                list.append(li);
            });
            details.append(summary, list);
            return details;
        }

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const message = input.value.trim();
            const file = fileInput.files[0] || null;
            if (!message && !file) return;

            errorBox.hidden = true;
            input.disabled = true;

            const body = new FormData();
            body.append('message', message || '(analisis berkas)');
            if (convField.value) body.append('conversation_id', convField.value);
            if (file) body.append('file', file);

            try {
                const response = await fetch('/dashboard/chat', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: body,
                });

                const data = await response.json();

                if (!response.ok) {
                    errorBox.textContent = data.message || 'Terjadi kesalahan, coba lagi.';
                    errorBox.hidden = false;
                    return;
                }

                input.value = '';
                fileInput.value = '';
                fileName.textContent = '';

                if (!convField.value) {
                    window.location.href = '/dashboard/chat/' + data.conversation_id;
                    return;
                }

                const displayMessage = file ? message + ' 📎 ' + file.name : message;
                thread.append(bubble('user', displayMessage), stepsBlock(data.tool_calls), bubble('assistant', data.answer));
                thread.scrollTop = thread.scrollHeight;
            } catch (e) {
                errorBox.textContent = 'Gagal terhubung ke server.';
                errorBox.hidden = false;
            } finally {
                input.disabled = false;
                input.focus();
            }
        });
    </script>
@endsection