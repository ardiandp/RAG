<?php

namespace App\Providers;

use App\Services\OllamaService;
use App\Tools\CustomerTool;
use App\Tools\ProductTool;
use App\Tools\SalesTool;
use App\Tools\ToolRegistry;
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

        $this->app->singleton(ToolRegistry::class, function (): ToolRegistry {
            return new ToolRegistry([
                $this->app->make(SalesTool::class),
                $this->app->make(ProductTool::class),
                $this->app->make(CustomerTool::class),
            ]);
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
