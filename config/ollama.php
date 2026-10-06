<?php

return [

    'url' => env('OLLAMA_URL', 'http://127.0.0.1:11434'),

    'model' => env('OLLAMA_MODEL', 'qwen2.5:1.5b'),

    'embedding_model' => env('OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text'),

    'embedding_dimensions' => (int) env('OLLAMA_EMBEDDING_DIMENSIONS', 768),

    'timeout' => (int) env('OLLAMA_TIMEOUT', 120),

    'temperature' => (float) env('OLLAMA_TEMPERATURE', 0.2),

    'top_k' => (int) env('OLLAMA_TOP_K', 3),

];
