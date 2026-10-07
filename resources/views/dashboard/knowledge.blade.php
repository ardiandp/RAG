@extends('dashboard.layouts.app')

@section('title', 'Knowledge')

@section('content')
    <h1>Knowledge Base</h1>

    <div class="split">
        @if ($canUpload)
            <div class="card">
                <h3>Upload Dokumen</h3>
                <form method="POST" action="/dashboard/knowledge" enctype="multipart/form-data">
                    @csrf
                    <label>Berkas (PDF, DOCX, TXT)</label>
                    <input type="file" name="file" accept=".txt,.md,.pdf,.docx" required>
                    <label>Judul (opsional)</label>
                    <input type="text" name="title" placeholder="auto dari nama berkas">
                    <label>Sumber (opsional)</label>
                    <input type="text" name="source" placeholder="contoh: Upload Manual" value="upload">
                    <button type="submit" class="btn">Indeks</button>
                </form>
            </div>
        @endif

        <div class="card">
            <h3>Sumber</h3>
            <ul class="toolcall-list">
                @forelse ($sources as $source)
                    <li>
                        <strong>{{ $source->name }}</strong>
                        <span class="muted">· {{ $source->documents_count }} dokumen</span>
                    </li>
                @empty
                    <li class="muted">Belum ada sumber.</li>
                @endforelse
            </ul>
        </div>
    </div>

    <h2>Dokumen</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Judul</th>
                <th>Sumber</th>
                <th>Status</th>
                <th>Chunks</th>
                <th>Aktif</th>
                <th>Preview</th>
                @if ($canUpload)
                    <th></th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse ($documents as $document)
                <tr>
                    <td>#{{ $document->id }}</td>
                    <td>{{ $document->title }}</td>
                    <td>{{ $document->source?->name ?? '—' }}</td>
                    <td><span class="badge badge-{{ $document->status }}">{{ $document->status }}</span></td>
                    <td>{{ $document->chunks_count }}</td>
                    <td>{{ $document->created_at->diffForHumans() }}</td>
                    <td class="truncate">{{ \Illuminate\Support\Str::limit($document->content, 120) }}</td>
                    @if ($canUpload)
                        <td>
                            <form method="POST" action="{{ route('knowledge.destroy', $document) }}" class="inline"
                                  onsubmit="return confirm('Hapus dokumen \'{{ $document->title }}\' dan seluruh chunks-nya?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger">Hapus</button>
                            </form>
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="8" class="empty">Belum ada dokumen terindeks.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="pagination">{{ $documents->links() }}</div>
@endsection