@extends('dashboard.layouts.app')

@section('title', $agent->exists ? 'Edit Agent' : 'Buat Agent')

@section('content')
    <p><a href="/dashboard/agents">← Kembali</a></p>
    <h1>{{ $agent->exists ? 'Edit Agent' : 'Buat Agent' }}</h1>

    <form method="POST"
          action="{{ $agent->exists ? "/dashboard/agents/{$agent->id}" : '/dashboard/agents' }}"
          class="card form-card">
        @csrf
        @if ($agent->exists)
            @method('PUT')
        @endif

        <label>Nama *</label>
        <input type="text" name="name" value="{{ old('name', $agent->name) }}" required maxlength="255">

        <label>Deskripsi</label>
        <input type="text" name="description" value="{{ old('description', $agent->description) }}" maxlength="1000">

        <label>System Prompt</label>
        <textarea name="system_prompt" rows="6" placeholder="Kamu adalah asisten yang ...">{{ old('system_prompt', $agent->system_prompt) }}</textarea>

        <label>Model Ollama</label>
        <input type="text" name="model" value="{{ old('model', $agent->model) }}" placeholder="kosong = default ({{ config('ollama.model') }})">

        <label>Max Steps *</label>
        <input type="number" name="max_steps" value="{{ old('max_steps', $agent->max_steps ?? 5) }}" min="1" max="20" required>

        <label class="check">
            <input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $agent->is_active ?? true))>
            Aktif
        </label>

        <label>Tool yang Diizinkan (kosong = semua)</label>
        <div class="toolcheck">
            @foreach ($tools as $name => $tool)
                <label class="check">
                    <input type="checkbox" name="tools[]" value="{{ $name }}"
                        @checked(in_array($name, old('tools', $agent->tools ?? []), true))>
                    <code>{{ $name }}</code>
                    <span class="muted">{{ $tool->description() }}</span>
                </label>
            @endforeach
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Simpan</button>
            <a class="btn" href="/dashboard/agents">Batal</a>
        </div>
    </form>
@endsection