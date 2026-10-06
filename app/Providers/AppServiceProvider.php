<?php

namespace App\Providers;

use App\Models\User;
use App\Services\EmbeddingService;
use App\Services\KnowledgeService;
use App\Services\OllamaService;
use App\Tools\CustomerTool;
use App\Tools\KnowledgeTool;
use App\Tools\ProductTool;
use App\Tools\SalesTool;
use App\Tools\ToolRegistry;
use Illuminate\Support\Facades\Gate;
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

        $this->app->singleton(EmbeddingService::class, function (): EmbeddingService {
            return new EmbeddingService(
                url: (string) config('ollama.url'),
                model: (string) config('ollama.embedding_model'),
                timeout: (int) config('ollama.timeout'),
            );
        });

        $this->app->singleton(KnowledgeService::class, function (): KnowledgeService {
            return new KnowledgeService($this->app->make(EmbeddingService::class));
        });

        $this->app->singleton(ToolRegistry::class, function (): ToolRegistry {
            return new ToolRegistry([
                $this->app->make(SalesTool::class),
                $this->app->make(ProductTool::class),
                $this->app->make(CustomerTool::class),
                $this->app->make(KnowledgeTool::class),
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('sales.view', fn (User $user) => $user->role === 'admin');
        Gate::define('customer.view', fn (User $user) => $user->role === 'admin');
        Gate::define('knowledge.view', fn (User $user) => $user->role === 'admin');
    }
}
