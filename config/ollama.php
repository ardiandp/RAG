<?php

return [

    'url' => env('OLLAMA_URL', 'http://127.0.0.1:11434'),

    'model' => env('OLLAMA_MODEL', 'qwen2.5:1.5b'),

    'timeout' => (int) env('OLLAMA_TIMEOUT', 120),

    'temperature' => (float) env('OLLAMA_TEMPERATURE', 0.2),

];
