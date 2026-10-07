<?php

namespace App\Http\Controllers;

use App\Tools\ToolRegistry;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(ToolRegistry $registry): View
    {
        return view('dashboard.settings.index', [
            'aps' => [
                'APP_URL' => config('app.url'),
                'environment' => app()->environment(),
                'debug' => config('app.debug') ? 'on' : 'off',
            ],
            'ollama' => [
                'url' => config('ollama.url'),
                'model' => config('ollama.model'),
                'embedding_model' => config('ollama.embedding_model'),
                'embedding_dimensions' => config('ollama.embedding_dimensions'),
                'timeout' => config('ollama.timeout').' s',
                'temperature' => config('ollama.temperature'),
                'top_k' => config('ollama.top_k'),
            ],
            'agent' => [
                'default_max_steps' => config('agent.max_steps', 5),
            ],
            'tools' => $registry->all(),
        ]);
    }
}
