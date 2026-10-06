<?php

namespace App\Providers;

use App\Services\OllamaService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(OllamaService::class, function (): OllamaService {
            return new OllamaService(
                url: (string) config('ollama.url'),
                model: (string) config('ollama.model'),
                timeout: (int) config('ollama.timeout'),
                temperature: (float) config('ollama.temperature'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
