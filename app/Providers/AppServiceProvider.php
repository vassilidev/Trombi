<?php

namespace App\Providers;

use App\Services\OpenRouter\OpenRouterClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(OpenRouterClient::class, function (): OpenRouterClient {
            /** @var array<string, mixed> $config */
            $config = config('services.openrouter');

            return new OpenRouterClient(
                apiKey: $config['key'],
                baseUrl: $config['base_url'],
                defaultVisionModel: $config['vision_model'],
                defaultEmbeddingModel: $config['embedding_model'],
                embeddingDimensions: (int) $config['embedding_dimensions'],
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
